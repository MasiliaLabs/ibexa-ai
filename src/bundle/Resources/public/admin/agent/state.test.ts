import { test } from 'node:test';
import assert from 'node:assert/strict';

import { decisionsPayload, initialState, pendingApprovals, reducer, restoreTurns } from './state';
import type { AgentState } from './state';

function started(text: string): AgentState {
    return reducer(initialState, { type: 'turn.started', turnId: 't1', text });
}

test('a turn collects text, tool steps and their outcomes', () => {
    let state = started('Which siteaccesses?');
    state = reducer(state, { type: 'event', event: { type: 'tool.started', id: 'c1', tool: 'explore_site', inputs: {} } });
    state = reducer(state, { type: 'event', event: { type: 'tool.finished', id: 'c1', tool: 'explore_site', ok: true, message: 'ok', data: {} } });
    state = reducer(state, { type: 'event', event: { type: 'text.delta', text: 'Three ' } });
    state = reducer(state, { type: 'event', event: { type: 'text.delta', text: 'of them.' } });
    state = reducer(state, { type: 'event', event: { type: 'run.completed', interrupted: false } });

    const [turn] = state.turns;
    assert.equal(turn.text, 'Three of them.');
    assert.equal(turn.tools[0].status, 'ok');
    assert.equal(turn.status, 'done');
    assert.equal(state.busy, false);
});

test('text written after a tool call is separated from the text before it', () => {
    let state = started('Explain');
    state = reducer(state, { type: 'event', event: { type: 'text.delta', text: 'Fossil Exit.' } });
    state = reducer(state, { type: 'event', event: { type: 'tool.started', id: 'c3', tool: 'explore_site', inputs: {} } });
    state = reducer(state, { type: 'event', event: { type: 'tool.finished', id: 'c3', tool: 'explore_site', ok: true, message: '', data: {} } });
    state = reducer(state, { type: 'event', event: { type: 'text.delta', text: 'Fossil Exit has three siteaccesses.' } });

    assert.equal(state.turns[0].text, 'Fossil Exit.\n\nFossil Exit has three siteaccesses.');
});

test('a failed tool is shown as failed, not as done', () => {
    let state = started('Create it');
    state = reducer(state, { type: 'event', event: { type: 'tool.started', id: 'c2', tool: 'propose_plan', inputs: {} } });
    state = reducer(state, { type: 'event', event: { type: 'tool.finished', id: 'c2', tool: 'propose_plan', ok: false, message: 'Not run', data: {} } });

    assert.equal(state.turns[0].tools[0].status, 'failed');
});

test('an approval request waits the turn and is cleared by a decision', () => {
    let state = started('Create a folder');
    state = reducer(state, { type: 'event', event: { type: 'approval.requested', id: 'a1', tool: 'propose_plan', reason: 'Create a folder', inputs: {} } });
    state = reducer(state, { type: 'event', event: { type: 'run.completed', interrupted: true } });

    assert.equal(state.turns[0].status, 'awaiting');
    assert.equal(pendingApprovals(state).length, 1);
    assert.equal(decisionsPayload(state), null);

    state = reducer(state, { type: 'decision.recorded', approvalId: 'a1', decision: { kind: 'reject', reason: 'Not now' } });

    assert.equal(pendingApprovals(state).length, 0);
    assert.deepEqual(decisionsPayload(state), { a1: ['reject', 'Not now'] });
});

test('text written after the decision is marked as coming after the approval', () => {
    let state = started('Create a folder');
    state = reducer(state, { type: 'event', event: { type: 'text.delta', text: 'Here is the plan.' } });
    state = reducer(state, { type: 'event', event: { type: 'approval.requested', id: 'a1', tool: 'propose_plan', reason: 'Plan', inputs: {} } });
    state = reducer(state, { type: 'event', event: { type: 'run.completed', interrupted: true } });
    state = reducer(state, { type: 'decision.recorded', approvalId: 'a1', decision: { kind: 'approve' } });
    state = reducer(state, { type: 'decisions.sent' });
    state = reducer(state, { type: 'event', event: { type: 'text.delta', text: 'Done.' } });

    const [turn] = state.turns;
    assert.equal(turn.status, 'streaming');
    assert.equal(turn.text.slice(0, turn.resumeAt ?? 0), 'Here is the plan.');
    assert.equal(turn.text.slice(turn.resumeAt ?? 0), 'Done.');
});

test('decisions are sent for every pending change, approvals and rejections alike', () => {
    let state = started('Two changes');
    state = reducer(state, { type: 'event', event: { type: 'approval.requested', id: 'a1', tool: 'propose_plan', reason: 'One', inputs: {} } });
    state = reducer(state, { type: 'event', event: { type: 'approval.requested', id: 'a2', tool: 'propose_plan', reason: 'Two', inputs: {} } });
    state = reducer(state, { type: 'decision.recorded', approvalId: 'a1', decision: { kind: 'approve' } });

    assert.equal(decisionsPayload(state), null);

    state = reducer(state, { type: 'decision.recorded', approvalId: 'a2', decision: { kind: 'reject', reason: '' } });

    assert.deepEqual(decisionsPayload(state), { a1: 'approve', a2: ['reject', ''] });
});

test('a run error marks the turn failed and frees the input', () => {
    let state = started('Hello');
    state = reducer(state, { type: 'event', event: { type: 'run.error', code: 'service_error', message: 'Down' } });

    assert.equal(state.turns[0].status, 'failed');
    assert.equal(state.turns[0].error, 'Down');
    assert.equal(state.busy, false);
});

test('stopping a stream keeps what was received and frees the input', () => {
    let state = started('Long answer');
    state = reducer(state, { type: 'event', event: { type: 'text.delta', text: 'Partial' } });
    state = reducer(state, { type: 'stream.stopped' });

    assert.equal(state.turns[0].text, 'Partial');
    assert.equal(state.busy, false);
});

test('a restored conversation rebuilds turns and attaches pending changes to the last one', () => {
    const turns = restoreTurns(
        [
            { role: 'user', text: 'Hi' },
            { role: 'assistant', text: 'Hello.' },
            { role: 'user', text: 'Create it' },
            { role: 'assistant', text: 'I will propose a plan.' },
        ],
        [{ id: 'a9', tool: 'propose_plan', reason: 'Plan', inputs: {} }],
    );

    assert.equal(turns.length, 2);
    assert.equal(turns[0].user, 'Hi');
    assert.equal(turns[0].text, 'Hello.');
    assert.equal(turns[1].status, 'awaiting');
    assert.equal(turns[1].approvals[0].id, 'a9');
    assert.equal(turns[0].approvals.length, 0);
});

test('a pending change is restored even when no message is stored', () => {
    const turns = restoreTurns([], [{ id: 'a1', tool: 'propose_plan', reason: 'Plan', inputs: {} }]);

    assert.equal(turns.length, 1);
    assert.equal(turns[0].user, null);
    assert.equal(turns[0].status, 'awaiting');
    assert.equal(turns[0].approvals[0].id, 'a1');
});

test('clearing returns to the initial state', () => {
    const state = reducer(started('x'), { type: 'cleared' });

    assert.deepEqual(state, initialState);
});
