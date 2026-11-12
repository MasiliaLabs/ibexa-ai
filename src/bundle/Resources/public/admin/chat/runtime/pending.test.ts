import assert from 'node:assert/strict';
import { test } from 'node:test';

import { isStaleDecision, withInterrupts, withPendingCalls } from './pending';

const gate = { id: 'call_1', reason: 'tool_call', toolCallId: 'call_1', message: 'Create a folder', metadata: { name: 'propose_plan', inputs: { title: 'X' } } };

test('without an interrupt nothing changes', () => {
    const messages = [{ id: 'u', role: 'user', content: 'Hi' }];

    assert.equal(withPendingCalls(messages, []), messages);
    assert.equal(withInterrupts([{ role: 'assistant', content: [] }], []).length, 1);
});

test('the pending change gets an assistant message after the last user message', () => {
    const result = withPendingCalls([{ id: 'u', role: 'user', content: 'Create it' }], [gate]);

    assert.equal(result.length, 2);
    assert.equal(result[1].role, 'assistant');
    assert.deepEqual(result[1].toolCalls, [{ id: 'call_1', type: 'function', function: { name: 'propose_plan', arguments: '{"title":"X"}' } }]);
});

test('it is never attached to an older answer', () => {
    const result = withPendingCalls(
        [{ id: 'u1', role: 'user', content: 'First' }, { id: 'a1', role: 'assistant', content: 'Done.' }, { id: 'u2', role: 'user', content: 'Second' }],
        [gate],
    );

    assert.equal(result.length, 4);
    assert.equal(result[1].toolCalls, undefined, 'the earlier answer is untouched');
    assert.equal(result[3].role, 'assistant');
});

test('when the last message is already the assistant, the call is added once', () => {
    const existing = { id: 'a', role: 'assistant', content: '', toolCalls: [{ id: 'call_1', type: 'function', function: { name: 'propose_plan', arguments: '{}' } }] };

    const result = withPendingCalls([{ id: 'u', role: 'user', content: 'x' }, existing], [gate]);

    assert.equal((result[1].toolCalls as unknown[]).length, 1);
});

test('the older confirmation interrupt shape is accepted too', () => {
    const result = withPendingCalls([{ id: 'u', role: 'user', content: 'x' }], [{ id: 'c9', reason: 'confirmation', metadata: {} }]);

    assert.equal(((result[1].toolCalls as Array<{ id: string }>)[0]).id, 'c9');
});

test('other interrupts are not tool approvals', () => {
    const messages = [{ id: 'u', role: 'user', content: 'x' }];

    assert.equal(withPendingCalls(messages, [{ id: 'q', reason: 'input_required' }]), messages);
});

test('the call an interrupt names gets its approval, and the message waits', () => {
    const result = withInterrupts(
        [
            { role: 'user', content: [{ type: 'text', text: 'x' }] },
            { role: 'assistant', content: [{ type: 'text', text: 'a' }, { type: 'tool-call', toolCallId: 'call_1' }, { type: 'tool-call', toolCallId: 'other' }] },
        ],
        [gate],
    );
    const parts = result[1].content as Array<Record<string, unknown>>;

    assert.deepEqual(parts[1].approval, { id: 'call_1', prompt: 'Create a folder' });
    assert.equal('approval' in parts[2], false, 'only the named call');
    assert.deepEqual(result[1].status, { type: 'requires-action', reason: 'interrupt' });
    assert.deepEqual((result[1].metadata?.custom as { agui: { interrupts: unknown[] } }).agui.interrupts, [gate]);
});

test('interrupts with no assistant message to hold them change nothing', () => {
    const messages = [{ role: 'user', content: 'x' }];

    assert.equal(withInterrupts(messages, [gate]), messages);
});

test('a decision that came too late is recognised, other errors are not', () => {
    assert.equal(isStaleDecision('This change was already decided, probably in another window. The conversation was refreshed.'), true);
    assert.equal(isStaleDecision('The AI agent is currently unavailable.'), false);
});
