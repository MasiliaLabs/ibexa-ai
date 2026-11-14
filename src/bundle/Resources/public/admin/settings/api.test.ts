import assert from 'node:assert/strict';
import { test } from 'node:test';

import { api, cleanProviderMessage, messageFromBody, request } from './api';
import type { Fetcher } from './api';

const answer = (status: number, body: unknown): Fetcher => async () =>
    new Response(body === undefined ? '' : JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } });

test('the server error envelope gives its message, never [object Object]', () => {
    assert.equal(messageFromBody({ error: { code: 'VALIDATION_ERROR', message: 'Name is required.' } }, 'x'), 'Name is required.');
    assert.equal(messageFromBody({ error: 'Plain text' }, 'x'), 'Plain text');
    assert.equal(messageFromBody({ message: 'Top level' }, 'x'), 'Top level');
    assert.equal(messageFromBody({ error: { code: 'X' } }, 'fallback'), 'fallback');
    assert.equal(messageFromBody(null, 'fallback'), 'fallback');
    assert.equal(messageFromBody('text', 'fallback'), 'fallback');
});

test('provider messages lose the JSON noise and keep the readable part', () => {
    assert.equal(
        cleanProviderMessage('API returned HTTP 401: {"error": {"message": "Invalid API key"}}'),
        'API returned HTTP 401: Invalid API key',
    );
    assert.equal(cleanProviderMessage('{"message": "Quota exceeded"}'), 'Quota exceeded');
    assert.equal(cleanProviderMessage('Connection failed: timeout'), 'Connection failed: timeout');
    assert.equal(cleanProviderMessage('Broken {json'), 'Broken {json');
    assert.equal(cleanProviderMessage('  '), 'Unknown error.');
});

test('request returns the data on success', async () => {
    const result = await request<{ a: number }>('/x', undefined, answer(200, { a: 1 }));

    assert.deepEqual(result, { ok: true, data: { a: 1 } });
});

test('request turns an HTTP error into a message, with the body kept', async () => {
    const result = await request('/x', undefined, answer(400, { error: { code: 'VALIDATION_ERROR', message: 'Temperature must be between 0 and 2.' } }));

    assert.equal(result.ok, false);
    if (!result.ok) {
        assert.equal(result.status, 400);
        assert.equal(result.message, 'Temperature must be between 0 and 2.');
    }
});

test('request has a message when the error body is not JSON', async () => {
    const result = await request('/x', undefined, async () => new Response('<html>', { status: 500 }));

    assert.equal(result.ok, false);
    if (!result.ok) {
        assert.equal(result.message, 'The server answered 500.');
    }
});

test('request survives a network failure', async () => {
    const result = await request('/x', undefined, async () => {
        throw new TypeError('Failed to fetch');
    });

    assert.equal(result.ok, false);
    if (!result.ok) {
        assert.equal(result.status, 0);
        assert.match(result.message, /Network error/);
    }
});

test('an access denied answer carries the server message', async () => {
    const result = await request('/x', undefined, answer(403, { error: { code: 'ACCESS_DENIED', message: 'You are not allowed to do this.' } }));

    assert.equal(result.ok, false);
    if (!result.ok) {
        assert.equal(result.message, 'You are not allowed to do this.');
    }
});

test('a failed connection test is a result, with the provider noise removed', async () => {
    const original = globalThis.fetch;
    globalThis.fetch = answer(400, { success: false, message: 'Connection failed: HTTP 401: {"error": {"message": "Invalid API key"}}', streamTested: false, streamOk: null });
    try {
        const result = await api.testProvider(3);

        assert.equal(result.success, false);
        assert.equal(result.message, 'Connection failed: HTTP 401: Invalid API key');
    } finally {
        globalThis.fetch = original;
    }
});

test('a successful connection test is returned as is', async () => {
    const original = globalThis.fetch;
    globalThis.fetch = answer(200, { success: true, message: 'Connection and streaming OK.', streamTested: true, streamOk: true });
    try {
        const result = await api.testProvider(3);

        assert.equal(result.success, true);
        assert.equal(result.streamOk, true);
    } finally {
        globalThis.fetch = original;
    }
});

test('every request says it comes from the admin interface, whatever the caller adds', async () => {
    const seen: Array<Record<string, string>> = [];
    const fetcher: Fetcher = async (_url, init) => {
        seen.push(init?.headers as Record<string, string>);

        return new Response('{}', { status: 200, headers: { 'Content-Type': 'application/json' } });
    };

    await request('/x', { method: 'DELETE' }, fetcher);
    await request('/x', { method: 'POST', headers: { 'Content-Type': 'application/json' } }, fetcher);

    assert.equal(seen[0]['X-Requested-With'], 'XMLHttpRequest', 'a DELETE has no JSON body to tell it from a forged one');
    assert.equal(seen[1]['X-Requested-With'], 'XMLHttpRequest');
    assert.equal(seen[1]['Content-Type'], 'application/json', 'what the caller sets is kept');
});
