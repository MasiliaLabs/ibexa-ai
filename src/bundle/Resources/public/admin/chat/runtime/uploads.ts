import type { ImageAttachment } from '../attachments';
import { csrfHeaders, route } from '@masilia-admin-kit/admin';

/** Where an attached image can be seen (for its owner only). */
export function attachmentUrl(id: string): string {
    return route('masilia.ibexa_ai.agent.attachment', { id });
}

/**
 * Sends an image to the server, which keeps it for the conversation and checks it is a real, reasonable image.
 * Rejects with the reason the server gives, in words for the user.
 */
export async function uploadImage(file: File): Promise<ImageAttachment> {
    const form = new FormData();
    form.append('file', file);

    const response = await fetch(route('masilia.ibexa_ai.agent.attachment_upload'), { method: 'POST', body: form, headers: csrfHeaders({ Accept: 'application/json' }) });
    const body = (await response.json().catch(() => null)) as { id?: string; name?: string; size?: number; width?: number; height?: number; error?: { message?: string } } | null;
    if (!response.ok || body === null || typeof body.id !== 'string') {
        throw new Error(body?.error?.message ?? `The image could not be sent (HTTP ${response.status}).`);
    }

    return { kind: 'image', id: body.id, name: body.name ?? file.name, bytes: body.size ?? file.size, width: body.width ?? 0, height: body.height ?? 0, previewUrl: URL.createObjectURL(file) };
}
