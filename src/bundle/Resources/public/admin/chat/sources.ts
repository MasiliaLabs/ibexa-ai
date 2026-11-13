import { contentHref, toolData } from './toolResult';

/**
 * The contents an answer rests on, as links. They come from what the tools returned (structured data, not the
 * model's text) and only the ones the answer cites are kept: a search can return two hundred contents, the user
 * wants the few the assistant talks about. A content is cited when its id appears in the text as a number of its
 * own, or its name appears in it as it is written.
 */

export interface Source {
    contentId: number;
    locationId: number | null;
    name: string;
    type: string | null;
    href: string;
}

export interface SourcePart {
    type: string;
    text?: string;
    result?: unknown;
}

const MAX_SOURCES = 8;
const MAX_NODES = 5000;
const MAX_DEPTH = 10;
// A shorter name ("About", "Home") appears in ordinary sentences: only the id can cite it. So does a name
// several contents share (every media folder is called "Images"): the text cannot say which one it means.
const MIN_NAME_LENGTH = 6;
const WORD = /[\p{L}\p{N}]/u;

const whole = (value: unknown): value is number => Number.isInteger(value) && (value as number) > 0;

function candidates(parts: readonly SourcePart[]): Map<number, Omit<Source, 'href'>> {
    const found = new Map<number, Omit<Source, 'href'>>();
    let nodes = 0;

    const walk = (node: unknown, depth: number): void => {
        if (depth > MAX_DEPTH || nodes++ > MAX_NODES || typeof node !== 'object' || node === null) {
            return;
        }
        if (Array.isArray(node)) {
            node.forEach((child) => walk(child, depth + 1));

            return;
        }
        const record = node as Record<string, unknown>;
        if (whole(record.content_id) && typeof record.name === 'string' && record.name !== '') {
            const known = found.get(record.content_id);
            const locationId = whole(record.location_id) ? record.location_id : null;
            if (known === undefined) {
                found.set(record.content_id, {
                    contentId: record.content_id,
                    locationId,
                    name: record.name,
                    type: typeof record.content_type === 'string' ? record.content_type : null,
                });
            } else if (known.locationId === null && locationId !== null) {
                known.locationId = locationId;
            }
        }
        Object.values(record).forEach((child) => walk(child, depth + 1));
    };

    for (const part of parts) {
        if (part.type === 'tool-call') {
            walk(toolData(part.result), 0);
        }
    }

    return found;
}

/** Where `needle` is in `text` as a whole word or number, or -1. */
function position(text: string, needle: string): number {
    let from = 0;
    for (;;) {
        const at = text.indexOf(needle, from);
        if (at === -1) {
            return -1;
        }
        const before = at > 0 ? text[at - 1] : '';
        const after = text[at + needle.length] ?? '';
        if (!WORD.test(before) && !WORD.test(after)) {
            return at;
        }
        from = at + 1;
    }
}

export function collectSources(parts: readonly SourcePart[]): Source[] {
    const text = parts
        .filter((part) => part.type === 'text' && typeof part.text === 'string')
        .map((part) => part.text as string)
        .join('\n');
    if (text === '') {
        return [];
    }

    const found = [...candidates(parts).values()];
    const sharedNames = new Set<string>();
    const seenNames = new Set<string>();
    for (const candidate of found) {
        (seenNames.has(candidate.name) ? sharedNames : seenNames).add(candidate.name);
    }

    const cited: Array<{ at: number; source: Source }> = [];
    for (const candidate of found) {
        const byId = position(text, String(candidate.contentId));
        const byName = candidate.name.length >= MIN_NAME_LENGTH && !sharedNames.has(candidate.name) ? position(text, candidate.name) : -1;
        const at = byId === -1 ? byName : byName === -1 ? byId : Math.min(byId, byName);
        if (at === -1) {
            continue;
        }
        const href = candidate.locationId !== null ? contentHref({ content_id: candidate.contentId, location_id: candidate.locationId }) : null;
        cited.push({ at, source: { ...candidate, href: href ?? `/view/content/${candidate.contentId}` } });
    }

    return cited
        .sort((a, b) => a.at - b.at)
        .slice(0, MAX_SOURCES)
        .map((entry) => entry.source);
}
