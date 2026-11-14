import assert from 'node:assert/strict';
import { afterEach, test } from 'node:test';

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

afterEach(() => {
    globalThis.fetch = realFetch;
});

test('deleting a conversation says it comes from the admin interface: a DELETE has no body to tell it from a forged one', async () => {
    const seen = capture(() => new Response('{}', { status: 200 }));

    await deleteThread('abc');

    assert.equal(seen[0].init?.method, 'DELETE');
    assert.equal((seen[0].init?.headers as Record<string, string>)['X-Requested-With'], 'XMLHttpRequest');
});

test('an uploaded image says it comes from the admin interface: a multipart form can be forged from another site', async () => {
    const seen = capture(() => new Response(JSON.stringify({ id: 'img-1', name: 'a.png', size: 3, width: 1, height: 1 }), { status: 200 }));

    const attachment = await uploadImage(new File([new Uint8Array([1, 2, 3])], 'a.png', { type: 'image/png' }));

    assert.equal(attachment.id, 'img-1');
    assert.equal(seen[0].init?.method, 'POST');
    assert.equal((seen[0].init?.headers as Record<string, string>)['X-Requested-With'], 'XMLHttpRequest');
});
