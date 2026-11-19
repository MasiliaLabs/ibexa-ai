/**
 * When a long conversation is continued, its summary comes back as a message that starts with a heading
 * ("## Previous conversation summary:"). It is shown as a folded card, rendered as Markdown, instead of a wall of raw text.
 */
const HEADING = /^\s*#{1,3}\s*(previous conversation summary|conversation summary)\s*:?\s*\n/i;

export interface ConversationSummary {
    title: string;
    body: string;
}

/** The summary a message holds, or null when it is an ordinary message. */
export function parseSummary(text: string): ConversationSummary | null {
    const match = HEADING.exec(text);
    if (match === null) {
        return null;
    }
    const body = text.slice(match[0].length).trim();

    return body === '' ? null : { title: 'Previous conversation summary', body };
}
