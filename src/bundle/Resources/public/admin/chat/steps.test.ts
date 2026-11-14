import assert from 'node:assert/strict';
import { test } from 'node:test';

import { groupSteps } from './steps';

test('a call that only looks around is folded into the steps', () => {
    assert.deepEqual(groupSteps({ type: 'tool-call', toolName: 'explore_site' }), ['group-steps']);
    assert.deepEqual(groupSteps({ type: 'tool-call', toolName: 'content__load' }), ['group-steps']);
});

test('a change stays in the open: a proposal, or any call that asks for approval, decided or not', () => {
    assert.deepEqual(groupSteps({ type: 'tool-call', toolName: 'propose_changes' }), []);
    assert.deepEqual(groupSteps({ type: 'tool-call', toolName: 'content__load', approval: { id: 'a' } }), []);
    assert.deepEqual(groupSteps({ type: 'tool-call', toolName: 'content__load', approval: { id: 'a', approved: true } }), []);
});

test('text is not a step', () => {
    assert.deepEqual(groupSteps({ type: 'text' }), []);
});
