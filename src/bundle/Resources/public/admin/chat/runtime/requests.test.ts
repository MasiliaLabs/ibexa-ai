import assert from 'node:assert/strict';
import { afterEach, beforeEach, test } from 'node:test';

import { installRouting } from '../../shared/testing';
import { deleteThread } from './threads';
import { uploadImage } from './uploads';

type Seen = { url: string; init: RequestInit | undefined };
const realFetch = globalThis.fetch;

function capture(answer: () => Response): Seen[] {
    const seen: Seen[] = [];
    globalThis.fetch = (async (url: unknown, init?: RequestInit) => {
        seen.push({ url: String(url), init });

        return answer();
    }) as typeof fetch;

    return seen;
}

beforeEach(installRouting);

const meta = { content: 'token-of-the-page' };
(globalThis as { document?: unknown }).document = { querySelector: () => meta };

afterEach(() => {
    globalThis.fetch = realFetch;
});

test('deleting a conversation carries the CSRF token of the admin', async () => {
    const seen = capture(() => new Response('{}', { status: 200 }));

    await deleteThread('abc');

    assert.equal(seen[0].init?.method, 'DELETE');
    assert.equal((seen[0].init?.headers as Record<string, string>)['X-CSRF-Token'], 'token-of-the-page');
});

test('an uploaded image carries the CSRF token of the admin: a multipart form can be forged from another site', async () => {
    const seen = capture(() => new Response(JSON.stringify({ id: 'img-1', name: 'a.png', size: 3, width: 1, height: 1 }), { status: 200 }));

    const attachment = await uploadImage(new File([new Uint8Array([1, 2, 3])], 'a.png', { type: 'image/png' }));

    assert.equal(attachment.id, 'img-1');
    assert.equal(seen[0].init?.method, 'POST');
    assert.equal((seen[0].init?.headers as Record<string, string>)['X-CSRF-Token'], 'token-of-the-page');
});
