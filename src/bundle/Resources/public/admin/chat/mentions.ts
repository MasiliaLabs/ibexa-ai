/**
 * Mentioning a content in a message with "@".
 *
 * In the message box the mention is what the user sees and edits: "@" and a short label. When the message is sent
 * it becomes a token the agent can use and the history can show: @[Name](content:ID/LOCATION). Pure: no React.
 */

export interface Mention {
    contentId: number;
    locationId: number | null;
    name: string;
}

/** A mention chosen in the message box: `label` is what stands after the "@" in the text. */
export interface Picked extends Mention {
    label: string;
}

export interface Trigger {
    /** Where the "@" is. */
    start: number;
    /** The caret. */
    end: number;
    query: string;
}

export type Piece = { type: 'text'; text: string } | { type: 'mention'; name: string; contentId: number; locationId: number | null };

const MAX_NAME = 80;
const MAX_LABEL = 30;
const LETTER_OR_DIGIT = '\\p{L}\\p{N}';
const TRIGGER = /(^|[\s(])@([^\s@[\]()]{0,40})$/u;
const TOKEN = /@\[([^\]\n]{1,80})\]\(content:(\d+)(?:\/(\d+))?\)/gu;

/** A name as it goes into a token: nothing that would end the token early. */
export function cleanName(name: string): string {
    return name.replace(/[[\]()\r\n]+/g, ' ').replace(/\s+/g, ' ').trim().slice(0, MAX_NAME);
}

/** The "@query" the caret is at the end of, if any: an "@" that starts a word. */
export function mentionTrigger(text: string, caret: number): Trigger | null {
    const before = text.slice(0, caret);
    const match = TRIGGER.exec(before);
    if (match === null) {
        return null;
    }

    return { start: before.length - match[2].length - 1, end: caret, query: match[2] };
}

/** A name cut at a word, not in the middle of one. */
function shorten(name: string): string {
    if (name.length <= MAX_LABEL) {
        return name;
    }
    const cut = name.slice(0, MAX_LABEL);
    const space = cut.lastIndexOf(' ');

    return (space > MAX_LABEL / 2 ? cut.slice(0, space) : cut).trimEnd();
}

/** What stands after the "@" for a content: its name, short; made unique when another mention has the same. */
export function labelFor(name: string, contentId: number, taken: readonly Picked[]): string {
    const base = shorten(cleanName(name)) || `content ${contentId}`;

    return taken.some((other) => other.label === base && other.contentId !== contentId) ? `${base} #${contentId}` : base;
}

export function insertMention(text: string, trigger: Trigger, label: string): { text: string; caret: number } {
    const inserted = `@${label} `;

    return { text: text.slice(0, trigger.start) + inserted + text.slice(trigger.end), caret: trigger.start + inserted.length };
}

export function tokenFor(mention: Mention): string {
    return `@[${cleanName(mention.name)}](content:${mention.contentId}${mention.locationId !== null ? `/${mention.locationId}` : ''})`;
}

const escapeRegExp = (value: string): string => value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');

/** The text to send: each "@label" the user picked, still in the text, becomes its token. */
export function encodeMentions(text: string, picked: readonly Picked[]): string {
    if (picked.length === 0) {
        return text;
    }

    const byLabel = new Map(picked.map((item) => [item.label, item]));
    const labels = [...byLabel.keys()].sort((a, b) => b.length - a.length).map(escapeRegExp);
    const pattern = new RegExp(`(?<![${LETTER_OR_DIGIT}])@(${labels.join('|')})(?![${LETTER_OR_DIGIT}])`, 'gu');

    return text.replace(pattern, (_whole, label: string) => tokenFor(byLabel.get(label) as Picked));
}

/** A sent message in pieces: text and mentions, to show the mentions as links. */
export function splitMentions(text: string): Piece[] {
    const pieces: Piece[] = [];
    let from = 0;
    for (const match of text.matchAll(TOKEN)) {
        const at = match.index ?? 0;
        if (at > from) {
            pieces.push({ type: 'text', text: text.slice(from, at) });
        }
        pieces.push({ type: 'mention', name: match[1], contentId: Number(match[2]), locationId: match[3] !== undefined ? Number(match[3]) : null });
        from = at + match[0].length;
    }
    if (from < text.length) {
        pieces.push({ type: 'text', text: text.slice(from) });
    }

    return pieces;
}

/** Where a mentioned content opens in the admin. */
export function mentionHref(mention: { contentId: number; locationId: number | null }): string {
    return mention.locationId !== null ? `/view/content/${mention.contentId}/full/true/${mention.locationId}` : `/view/content/${mention.contentId}`;
}
