/**
 * Documents attached to a message. A text file is read in the browser and goes with the message as a block the
 * agent reads as source material; the user sees it as a chip. Pure: reading the file is done by the caller.
 *
 *   <attachment name="brief.md" bytes="1234">
 *   …the text…
 *   </attachment>
 */

export interface TextAttachment {
    kind: 'text';
    name: string;
    bytes: number;
    text: string;
}

export interface AttachmentPiece {
    name: string;
    bytes: number;
}

export const MAX_FILES = 3;
export const MAX_TEXT_BYTES = 150_000;
export const MAX_TOTAL_CHARS = 300_000;

const TEXT_EXTENSIONS = ['txt', 'md', 'markdown', 'csv', 'tsv', 'json', 'html', 'htm', 'xml', 'yaml', 'yml', 'srt', 'log'];
const BLOCK = /\n*<attachment name="([^"]*)" bytes="(\d+)">\n[\s\S]*?\n<\/attachment>/g;

export function extensionOf(name: string): string {
    const dot = name.lastIndexOf('.');

    return dot === -1 ? '' : name.slice(dot + 1).toLowerCase();
}

/** Whether a file can be read as text: by its type, or by its extension when the browser gives none. */
export function isTextFile(file: { name: string; type: string }): boolean {
    return file.type.startsWith('text/') || file.type === 'application/json' || file.type === 'application/xml' || TEXT_EXTENSIONS.includes(extensionOf(file.name));
}

/** A name as it goes into the block: nothing that would end the attribute or the block early. */
export function cleanFileName(name: string): string {
    return name.replace(/["<>\r\n]+/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 120) || 'file';
}

export function formatBytes(bytes: number): string {
    return bytes < 1024 ? `${bytes} B` : bytes < 1024 * 1024 ? `${Math.round(bytes / 1024)} KB` : `${(bytes / 1024 / 1024).toFixed(1)} MB`;
}

export type ReadResult = { ok: true; attachment: TextAttachment } | { ok: false; reason: string };

/** The text of a file, or why it cannot be attached. */
export function toTextAttachment(file: { name: string; size: number }, text: string): ReadResult {
    if (file.size > MAX_TEXT_BYTES) {
        return { ok: false, reason: `${cleanFileName(file.name)} is too large (${formatBytes(file.size)}; up to ${formatBytes(MAX_TEXT_BYTES)}).` };
    }
    if (text.includes('\u0000')) {
        return { ok: false, reason: `${cleanFileName(file.name)} is not a text file.` };
    }

    return { ok: true, attachment: { kind: 'text', name: cleanFileName(file.name), bytes: file.size, text } };
}

/** The message to send: what the user wrote, then each document in its block. */
export function encodeAttachments(text: string, attachments: readonly TextAttachment[]): string {
    if (attachments.length === 0) {
        return text;
    }

    const blocks = attachments.map(
        // The text cannot close its own block.
        (attachment) => `<attachment name="${attachment.name}" bytes="${attachment.bytes}">\n${attachment.text.replace(/<\/attachment/gi, '<\\/attachment')}\n</attachment>`,
    );

    return `${text.trim() === '' ? 'See the attached document.' : text}\n\n${blocks.join('\n\n')}`;
}

/** A sent message: what the user wrote, and the documents it carries (name and size only). */
export function splitAttachments(text: string): { body: string; attachments: AttachmentPiece[] } {
    const attachments: AttachmentPiece[] = [];
    const body = text.replace(BLOCK, (_whole, name: string, bytes: string) => {
        attachments.push({ name, bytes: Number(bytes) });

        return '';
    });

    return { body: body.trim(), attachments };
}

/** Whether adding these would be too much for one message. */
export function tooMuch(current: readonly TextAttachment[], added: readonly TextAttachment[]): string | null {
    if (current.length + added.length > MAX_FILES) {
        return `You can attach up to ${MAX_FILES} files to a message.`;
    }
    const chars = [...current, ...added].reduce((sum, attachment) => sum + attachment.text.length, 0);

    return chars > MAX_TOTAL_CHARS ? 'These documents are too long together for one message.' : null;
}
