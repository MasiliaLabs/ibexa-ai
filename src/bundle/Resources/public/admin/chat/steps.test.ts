import assert from 'node:assert/strict';
import { test } from 'node:test';

import { groupSteps } from './steps';

test('a call that only looks around is folded into the steps', () => {
    assert.deepEqual(groupSteps({ type: 'tool-call', toolName: 'explore_site' }), ['group-steps']);
    assert.deepEqual(groupSteps({ type: 'tool-call', toolName: 'run_capability' }), ['group-steps']);
});

test('a change stays in the open: a plan, or any call that asks for approval, decided or not', () => {
    assert.deepEqual(groupSteps({ type: 'tool-call', toolName: 'propose_plan' }), []);
    assert.deepEqual(groupSteps({ type: 'tool-call', toolName: 'run_capability', approval: { id: 'a' } }), []);
    assert.deepEqual(groupSteps({ type: 'tool-call', toolName: 'run_capability', approval: { id: 'a', approved: true } }), []);
});

test('text is not a step', () => {
    assert.deepEqual(groupSteps({ type: 'text' }), []);
});
