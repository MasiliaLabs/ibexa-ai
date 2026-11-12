import assert from 'node:assert/strict';
import { test } from 'node:test';

import { activeFor, cellState, describeEngine, providerLabel, searchProviders } from './providers';
import type { Health, Model, Provider, ProviderType } from './types';

const types: ProviderType[] = [
    { value: 'openai', label: 'OpenAI', requiresApiKey: true, defaultApiUrl: null },
    { value: 'kimi', label: 'Kimi (Moonshot)', requiresApiKey: true, defaultApiUrl: 'https://api.moonshot.ai/v1' },
];

const provider = (over: Partial<Provider>): Provider => ({
    id: 1, name: 'OpenAI Prod', identifier: 'openai', siteaccesses: [], apiKey: null, apiUrl: null,
    activeChatModelId: null, activeImageModelId: null, ...over,
});
const model = (over: Partial<Model>): Model => ({
    id: 10, providerId: 1, providerName: 'OpenAI Prod', name: 'GPT', identifier: 'gpt-x', temperature: 0.7, maxTokens: 2048,
    supportsImage: false, ...over,
});
const health = (state: Health['state'], message = ''): Health => ({ state, providerName: 'x', message, checkedAt: '' });

test('the label comes from the server list, and an unknown type keeps its identifier', () => {
    assert.equal(providerLabel(types, 'kimi'), 'Kimi (Moonshot)');
    assert.equal(providerLabel(types, 'future'), 'future');
});

test('search finds by name, type label, siteaccess and model, and reports the model matches', () => {
    const providers = [provider({ id: 1 }), provider({ id: 2, name: 'Moon', identifier: 'kimi', siteaccesses: ['site_fr'] })];
    const models = [model({ id: 10, providerId: 1, name: 'Fast', identifier: 'fast-1' })];

    assert.equal(searchProviders(providers, models, types, '').providers.length, 2);
    assert.deepEqual(searchProviders(providers, models, types, 'moonshot').providers.map((p) => p.id), [2]);
    assert.deepEqual(searchProviders(providers, models, types, 'site_fr').providers.map((p) => p.id), [2]);

    const byModel = searchProviders(providers, models, types, 'fast-1');
    assert.deepEqual(byModel.providers.map((p) => p.id), [1]);
    assert.deepEqual([...byModel.matchingProviderIds], [1]);
});

test('the active model of a siteaccess needs the assignment and a model that exists', () => {
    const providers = [provider({ id: 1, siteaccesses: ['site'], activeChatModelId: 10, activeImageModelId: 11 })];
    const models = [model({ id: 10 }), model({ id: 11, supportsImage: true })];

    assert.equal(activeFor('chat', 'site', providers, models)?.model.id, 10);
    assert.equal(activeFor('image', 'site', providers, models)?.model.id, 11);
    assert.equal(activeFor('chat', 'other', providers, models), null);
    assert.equal(activeFor('chat', 'site', providers, []), null);
});

test('the banner takes the server state and explains what is missing', () => {
    const none = describeEngine({ providers: [], models: [], siteaccess: 'site', health: health('not_configured', 'No provider.') });
    assert.equal(none.state, 'not_configured');
    assert.match(none.guidance ?? '', /No provider is assigned to site/);

    const noModel = describeEngine({ providers: [provider({ siteaccesses: ['site'] })], models: [], siteaccess: 'site', health: health('not_configured') });
    assert.match(noModel.guidance ?? '', /has no chat model/);

    const ready = describeEngine({
        providers: [provider({ siteaccesses: ['site'], activeChatModelId: 10 })], models: [model({ id: 10 })], siteaccess: 'site', health: health('online', 'Ready.'),
    });
    assert.equal(ready.state, 'online');
    assert.equal(ready.guidance, null);
    assert.equal(ready.message, 'Ready.');

    const offline = describeEngine({
        providers: [provider({ siteaccesses: ['site'], activeChatModelId: 10 })], models: [model({ id: 10 })], siteaccess: 'site', health: health('offline'),
    });
    assert.match(offline.guidance ?? '', /Check the API key/);
});

test('before the health answer arrives the state is unknown, not a guess', () => {
    assert.equal(describeEngine({ providers: [], models: [], siteaccess: 'site', health: null }).state, 'unknown');
});

test('matrix cells', () => {
    assert.equal(cellState(provider({ siteaccesses: [] }), 'site'), 'unassigned');
    assert.equal(cellState(provider({ siteaccesses: ['site'] }), 'site'), 'no_model');
    assert.equal(cellState(provider({ siteaccesses: ['site'], activeChatModelId: 10 }), 'site'), 'ready');
});
