import assert from 'node:assert/strict';
import { test } from 'node:test';

import {
    emptyModelDraft, emptyProviderDraft, modelPayload, modelToDraft, providerPayload, providerToDraft, validateModel, validateProvider,
} from './forms';

test('a provider needs a name; the key is required only when none is stored', () => {
    const draft = { ...emptyProviderDraft('openai'), name: '  ' };

    assert.deepEqual(Object.keys(validateProvider(draft, true, false)).sort(), ['apiKey', 'name']);
    assert.deepEqual(validateProvider({ ...draft, name: 'Prod', apiKey: 'sk' }, true, false), {});
    assert.deepEqual(validateProvider({ ...draft, name: 'Prod' }, true, true), {}, 'a stored key is kept');
    assert.deepEqual(validateProvider({ ...draft, name: 'Local' }, false, false), {}, 'Ollama needs no key');
});

test('the endpoint must be a full http(s) address, or empty', () => {
    const draft = { ...emptyProviderDraft('openai'), name: 'P', apiKey: 'k' };

    assert.equal(validateProvider({ ...draft, apiUrl: 'localhost:11434' }, true, false).apiUrl !== undefined, true);
    assert.equal(validateProvider({ ...draft, apiUrl: 'ftp://x.test' }, true, false).apiUrl !== undefined, true);
    assert.deepEqual(validateProvider({ ...draft, apiUrl: 'http://localhost:11434/v1' }, true, false), {});
    assert.deepEqual(validateProvider({ ...draft, apiUrl: '' }, true, false), {});
});

test('editing a provider never sends the stored key back, and siteaccesses go only with a new one', () => {
    const existing = providerToDraft({
        id: 4, name: 'P', identifier: 'openai', siteaccesses: ['site'], apiKey: '••••••••', apiUrl: null,
        activeChatModelId: null, activeImageModelId: null,
    });

    assert.equal(existing.apiKey, '');
    const payload = providerPayload(existing);
    assert.equal(payload.apiKey, '');
    assert.equal('siteaccesses' in payload, false);

    const created = providerPayload({ ...emptyProviderDraft('kimi', ['site_fr']), name: ' New ', apiKey: ' k ' });
    assert.equal(created.name, 'New');
    assert.equal(created.apiKey, 'k');
    assert.deepEqual(created.siteaccesses, ['site_fr']);
    assert.equal(created.id, null);
});

test('model rules mirror the server: temperature 0..2, max tokens a whole number >= 1', () => {
    const ok = { ...emptyModelDraft(3), name: 'M', identifier: 'm-1' };

    assert.deepEqual(validateModel(ok), {});
    assert.ok(validateModel({ ...ok, temperature: '2.1' }).temperature);
    assert.ok(validateModel({ ...ok, temperature: '-0.1' }).temperature);
    assert.ok(validateModel({ ...ok, temperature: '' }).temperature);
    assert.ok(validateModel({ ...ok, temperature: 'abc' }).temperature);
    assert.deepEqual(validateModel({ ...ok, temperature: '0' }), {});
    assert.deepEqual(validateModel({ ...ok, temperature: '2' }), {});
    assert.ok(validateModel({ ...ok, maxTokens: '0' }).maxTokens);
    assert.ok(validateModel({ ...ok, maxTokens: '10.5' }).maxTokens);
    assert.ok(validateModel({ ...emptyModelDraft(null), name: 'M', identifier: 'm' }).providerId);
    assert.deepEqual(Object.keys(validateModel(emptyModelDraft(3))).sort(), ['identifier', 'name']);
});

test('the model payload has numbers, not strings', () => {
    const draft = modelToDraft({
        id: 9, providerId: 3, providerName: 'P', name: ' M ', identifier: 'm-1', temperature: 0.5, maxTokens: 4096, supportsImage: true, supportsVision: true,
    });

    assert.deepEqual(modelPayload(draft), {
        id: 9, providerId: 3, name: 'M', identifier: 'm-1', temperature: 0.5, maxTokens: 4096, supportsImage: true, supportsVision: true,
    });
});
