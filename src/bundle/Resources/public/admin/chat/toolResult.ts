/**
 * What the user reads of a tool's answer. Tools answer with a JSON object {success, message, data}; Neuron answers
 * a rejected or skipped call with its own text, which must not look like a success. The stream carries the answer
 * as it is: it is read here.
 */

const NOT_EXECUTED_PREFIX = 'TOOL NOT EXECUTED';
const MAX_MESSAGE_LENGTH = 500;

export interface ToolOutcome {
    ok: boolean;
    message: string;
    /** Where the content the tool created or changed can be opened in the admin, when the answer names it. */
    href: string | null;
}

/** The admin's view of a content at one of its locations. Only whole positive numbers go into the address. */
export function contentHref(data: unknown): string | null {
    if (typeof data !== 'object' || data === null) {
        return null;
    }
    const { content_id: contentId, location_id: locationId } = data as Record<string, unknown>;
    const whole = (value: unknown): value is number => Number.isInteger(value) && (value as number) > 0;

    return whole(contentId) && whole(locationId) ? `/view/content/${contentId}/full/true/${locationId}` : null;
}

function fromRecord(record: Record<string, unknown>): ToolOutcome {
    return {
        ok: record.success === true,
        message: clip(typeof record.message === 'string' ? record.message : ''),
        href: record.success === true ? contentHref(record.data) : null,
    };
}

function clip(text: string): string {
    return text.length > MAX_MESSAGE_LENGTH ? `${text.slice(0, MAX_MESSAGE_LENGTH)}…` : text;
}

export function toolOutcome(raw: unknown): ToolOutcome {
    if (typeof raw !== 'string') {
        // Already structured (some runtimes parse the answer): the same fields apply.
        if (typeof raw === 'object' && raw !== null && !Array.isArray(raw)) {
            return fromRecord(raw as Record<string, unknown>);
        }

        return { ok: true, message: '', href: null };
    }

    if (raw.startsWith(NOT_EXECUTED_PREFIX)) {
        return { ok: false, message: 'Not run: the change was rejected.', href: null };
    }

    try {
        const decoded: unknown = JSON.parse(raw);
        if (typeof decoded === 'object' && decoded !== null && !Array.isArray(decoded)) {
            return fromRecord(decoded as Record<string, unknown>);
        }
    } catch {
        // Not JSON: plain text.
    }

    return { ok: true, message: clip(raw), href: null };
}

/**
 * The data a tool returned, when it succeeded: what the answer is made of (the contents found, the site tree).
 * Null for a failure or for an answer that carries none.
 */
export function toolData(raw: unknown): unknown {
    let decoded: unknown = raw;
    if (typeof raw === 'string') {
        if (raw.startsWith(NOT_EXECUTED_PREFIX)) {
            return null;
        }
        try {
            decoded = JSON.parse(raw);
        } catch {
            return null;
        }
    }
    if (typeof decoded !== 'object' || decoded === null || Array.isArray(decoded)) {
        return null;
    }
    const record = decoded as Record<string, unknown>;

    return record.success === true ? (record.data ?? null) : null;
}
