/**
 * A small Markdown reader for assistant messages. Pure: it returns a tree, and the widget turns
 * that tree into React elements, so model output is never inserted as HTML.
 *
 * Supported: paragraphs, headings, bullet and numbered lists, fenced code, rules, and inline
 * bold, emphasis, code and links. Anything else, including unfinished markers while a message
 * is still streaming, stays as plain text.
 */

export type Inline =
    | { type: 'text'; text: string }
    | { type: 'strong'; children: Inline[] }
    | { type: 'em'; children: Inline[] }
    | { type: 'code'; text: string }
    | { type: 'link'; href: string; children: Inline[] };

export type Block =
    | { type: 'paragraph'; children: Inline[] }
    | { type: 'heading'; level: number; children: Inline[] }
    | { type: 'list'; ordered: boolean; items: Inline[][] }
    | { type: 'code'; text: string }
    | { type: 'rule' };

const FENCE = /^\s*```/;
const HEADING = /^(#{1,6})\s+(.*)$/;
const BULLET = /^\s*[-*+]\s+(.*)$/;
const NUMBERED = /^\s*\d+[.)]\s+(.*)$/;
const RULE = /^\s*(?:-{3,}|\*{3,}|_{3,})\s*$/;

// Leftmost match wins; at one position: code, then bold, then emphasis, then link.
const INLINE = /`([^`\n]+)`|\*\*(\S(?:[\s\S]*?\S)?)\*\*|\*([^\s*](?:[^*\n]*?[^\s*])?)\*|\[([^\]\n]+)\]\(([^)\s]+)\)/;

// Links open in the browser: only web addresses and site paths are turned into links.
const SAFE_HREF = /^(?:https?:\/\/|\/(?!\/))/i;

export function parseMarkdown(source: string): Block[] {
    const lines = source.replace(/\r\n/g, '\n').split('\n');
    const blocks: Block[] = [];
    let paragraph: string[] = [];

    const flushParagraph = () => {
        if (paragraph.length > 0) {
            blocks.push({ type: 'paragraph', children: parseInline(paragraph.join('\n')) });
            paragraph = [];
        }
    };

    let index = 0;
    while (index < lines.length) {
        const line = lines[index];

        if (FENCE.test(line)) {
            flushParagraph();
            const code: string[] = [];
            index += 1;
            // An unclosed fence (message still streaming) runs to the end.
            while (index < lines.length && !FENCE.test(lines[index])) {
                code.push(lines[index]);
                index += 1;
            }
            index += 1;
            blocks.push({ type: 'code', text: code.join('\n') });
            continue;
        }

        if (line.trim() === '') {
            flushParagraph();
            index += 1;
            continue;
        }

        if (RULE.test(line)) {
            flushParagraph();
            blocks.push({ type: 'rule' });
            index += 1;
            continue;
        }

        const heading = HEADING.exec(line);
        if (heading !== null) {
            flushParagraph();
            blocks.push({ type: 'heading', level: heading[1].length, children: parseInline(heading[2].trim()) });
            index += 1;
            continue;
        }

        const ordered = NUMBERED.test(line);
        if (ordered || BULLET.test(line)) {
            flushParagraph();
            const marker = ordered ? NUMBERED : BULLET;
            const items: Inline[][] = [];
            while (index < lines.length) {
                const item = marker.exec(lines[index]);
                if (item === null) {
                    break;
                }
                items.push(parseInline(item[1].trim()));
                index += 1;
            }
            blocks.push({ type: 'list', ordered, items });
            continue;
        }

        paragraph.push(line);
        index += 1;
    }

    flushParagraph();

    return blocks;
}

export function parseInline(source: string): Inline[] {
    const nodes: Inline[] = [];
    let rest = source;

    const pushText = (text: string) => {
        if (text === '') {
            return;
        }
        const last = nodes[nodes.length - 1];
        if (last !== undefined && last.type === 'text') {
            last.text += text;
        } else {
            nodes.push({ type: 'text', text });
        }
    };

    while (rest !== '') {
        const match = INLINE.exec(rest);
        if (match === null) {
            pushText(rest);
            break;
        }

        pushText(rest.slice(0, match.index));

        if (match[1] !== undefined) {
            nodes.push({ type: 'code', text: match[1] });
        } else if (match[2] !== undefined) {
            nodes.push({ type: 'strong', children: parseInline(match[2]) });
        } else if (match[3] !== undefined) {
            nodes.push({ type: 'em', children: parseInline(match[3]) });
        } else if (SAFE_HREF.test(match[5])) {
            nodes.push({ type: 'link', href: match[5], children: parseInline(match[4]) });
        } else {
            // Not a web address or a site path: keep what the model wrote, as text.
            pushText(match[0]);
        }

        rest = rest.slice(match.index + match[0].length);
    }

    return nodes;
}
