/**
 * Files attached to a message. A text file is read in the browser and goes with the message as a block the agent
 * reads as source material; an image is uploaded first, and the message names it by a tag (the server finds the
 * file by its id). The user sees chips. Pure: reading and uploading are done by the caller.
 *
 *   <attachment name="brief.md" bytes="1234">
 *   …the text…
 *   </attachment>
 *
 *   <attachment-image name="photo.jpg" id="…" width="1200" height="800" bytes="123456"/>
 */

export interface TextAttachment {
    kind: 'text';
    name: string;
    bytes: number;
    text: string;
}

export interface ImageAttachment {
    kind: 'image';
    /** What the server knows the image by. */
    id: string;
    name: string;
    bytes: number;
    width: number;
    height: number;
    /** A local address to show it before it is sent. */
    previewUrl?: string;
}

export type Attachment = TextAttachment | ImageAttachment;

/** An attachment of a message that was sent: what is needed to show it. */
export type AttachmentPiece = { kind: 'text'; name: string; bytes: number } | { kind: 'image'; id: string; name: string; bytes: number; width: number; height: number };

export const MAX_FILES = 3;
export const MAX_TEXT_BYTES = 150_000;
export const MAX_IMAGE_BYTES = 5 * 1024 * 1024;
export const MAX_TOTAL_CHARS = 300_000;

const TEXT_EXTENSIONS = ['txt', 'md', 'markdown', 'csv', 'tsv', 'json', 'html', 'htm', 'xml', 'yaml', 'yml', 'srt', 'log'];
const BLOCK = /\n*<attachment name="([^"]*)" bytes="(\d+)">\n[\s\S]*?\n<\/attachment>/g;
const IMAGE_TAG = /\n*<attachment-image name="([^"]*)" id="([0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12})" width="(\d+)" height="(\d+)" bytes="(\d+)"\/>/g;
const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
const IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

export function extensionOf(name: string): string {
    const dot = name.lastIndexOf('.');

    return dot === -1 ? '' : name.slice(dot + 1).toLowerCase();
}

/** Whether a file can be read as text: by its type, or by its extension when the browser gives none. */
export function isImageFile(file: { name: string; type: string }): boolean {
    return IMAGE_TYPES.includes(file.type) || (file.type === '' && IMAGE_EXTENSIONS.includes(extensionOf(file.name)));
}

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

/** The message to send: what the user wrote, then each document in its block and each image by its tag. */
export function encodeAttachments(text: string, attachments: readonly Attachment[]): string {
    if (attachments.length === 0) {
        return text;
    }

    const documents = attachments.filter((attachment): attachment is TextAttachment => attachment.kind === 'text');
    const images = attachments.filter((attachment): attachment is ImageAttachment => attachment.kind === 'image');
    const blocks = [
        ...documents.map(
            // The text cannot close its own block.
            (attachment) => `<attachment name="${attachment.name}" bytes="${attachment.bytes}">\n${attachment.text.replace(/<\/attachment/gi, '<\\/attachment')}\n</attachment>`,
        ),
        ...images.map((image) => `<attachment-image name="${image.name}" id="${image.id}" width="${image.width}" height="${image.height}" bytes="${image.bytes}"/>`),
    ];
    const fallback = images.length > 0 && documents.length > 0 ? 'See the attached files.' : images.length > 0 ? 'See the attached image.' : 'See the attached document.';

    return `${text.trim() === '' ? fallback : text}\n\n${blocks.join('\n\n')}`;
}

/** A sent message: what the user wrote, and the files it carries (name and size; a document's text is left out). */
export function splitAttachments(text: string): { body: string; attachments: AttachmentPiece[] } {
    const attachments: AttachmentPiece[] = [];
    let body = text.replace(BLOCK, (_whole, name: string, bytes: string) => {
        attachments.push({ kind: 'text', name, bytes: Number(bytes) });

        return '';
    });
    body = body.replace(IMAGE_TAG, (_whole, name: string, id: string, width: string, height: string, bytes: string) => {
        attachments.push({ kind: 'image', id, name, width: Number(width), height: Number(height), bytes: Number(bytes) });

        return '';
    });

    return { body: body.trim(), attachments };
}

/** Whether adding these would be too much for one message. */
export function tooMuch(current: readonly Attachment[], added: readonly Attachment[]): string | null {
    if (current.length + added.length > MAX_FILES) {
        return `You can attach up to ${MAX_FILES} files to a message.`;
    }
    const chars = [...current, ...added].reduce((sum, attachment) => sum + (attachment.kind === 'text' ? attachment.text.length : 0), 0);

    return chars > MAX_TOTAL_CHARS ? 'These documents are too long together for one message.' : null;
}
