import assert from 'node:assert/strict';
import { test } from 'node:test';

import type { Approval, ToolStep, Turn } from '../agent/state';
import { hasPendingApproval, toThreadMessages } from './messages';

const turn = (over: Partial<Turn>): Turn => ({
    id: 't1', user: 'Hello', text: '', tools: [], approvals: [], status: 'done', error: null, resumeAt: null, ...over,
});
const step = (over: Partial<ToolStep>): ToolStep => ({ id: 'c1', tool: 'explore_site', inputs: {}, status: 'ok', message: 'Found 3.', ...over });
const approval = (over: Partial<Approval>): Approval => ({
    id: 'c2', tool: 'propose_plan', reason: 'Create a folder', inputs: { title: 'X' }, decision: null, sent: false, ...over,
});

test('a turn is a user message then an assistant message', () => {
    const messages = toThreadMessages([turn({ text: 'Hi there.' })]);

    assert.equal(messages.length, 2);
    assert.equal(messages[0].role, 'user');
    assert.deepEqual(messages[0].content, [{ type: 'text', text: 'Hello' }]);
    assert.equal(messages[1].role, 'assistant');
    assert.deepEqual(messages[1].content, [{ type: 'text', text: 'Hi there.' }]);
    assert.deepEqual(messages[1].status, { type: 'complete', reason: 'stop' });
});

test('a restored turn without a user message has only the assistant message', () => {
    const messages = toThreadMessages([turn({ user: null, text: 'Earlier answer.' })]);

    assert.equal(messages.length, 1);
    assert.equal(messages[0].role, 'assistant');
});

test('message ids are stable and unique per turn', () => {
    const messages = toThreadMessages([turn({ id: 'a' }), turn({ id: 'b' })]);

    assert.deepEqual(messages.map((m) => m.id), ['a-user', 'a-assistant', 'b-user', 'b-assistant']);
});

test('a running tool has no result; a finished one has its message; a failed one is an error', () => {
    const [, assistant] = toThreadMessages([turn({
        tools: [step({ id: 'r', status: 'running', message: '' }), step({ id: 'o', message: 'Found 3.' }), step({ id: 'f', status: 'failed', message: 'Nope.' })],
    })]);
    const parts = assistant.content as Array<Record<string, unknown>>;

    assert.equal('result' in parts[0], false);
    assert.equal(parts[1].result, 'Found 3.');
    assert.equal(parts[2].result, 'Nope.');
    assert.equal(parts[2].isError, true);
    assert.equal(parts[0].toolName, 'explore_site');
});

test('a change awaiting a decision is a tool call carrying an approval', () => {
    const [, assistant] = toThreadMessages([turn({
        status: 'awaiting',
        tools: [step({ id: 'c2', tool: 'propose_plan', status: 'running', message: '' })],
        approvals: [approval({})],
    })]);
    const part = (assistant.content as Array<Record<string, unknown>>)[0];

    assert.equal(part.toolCallId, 'c2');
    assert.equal('result' in part, false, 'no result until it runs');
    assert.deepEqual(part.approval, { id: 'c2', prompt: 'Create a folder' });
    assert.deepEqual(assistant.status, { type: 'requires-action', reason: 'interrupt' });
});

test('an approved change says so; a refused one carries the reason and a result', () => {
    const approved = toThreadMessages([turn({
        tools: [step({ id: 'c2', status: 'running', message: '' })],
        approvals: [approval({ decision: { kind: 'approve' }, sent: true })],
    })])[1].content as Array<Record<string, unknown>>;
    assert.deepEqual(approved[0].approval, { id: 'c2', prompt: 'Create a folder', approved: true });
    assert.equal('result' in approved[0], false);

    const refused = toThreadMessages([turn({
        tools: [step({ id: 'c2', status: 'running', message: '' })],
        approvals: [approval({ decision: { kind: 'reject', reason: 'Not now' }, sent: true })],
    })])[1].content as Array<Record<string, unknown>>;
    assert.deepEqual(refused[0].approval, { id: 'c2', prompt: 'Create a folder', approved: false, reason: 'Not now' });
    assert.equal(refused[0].result, 'Refused.');
});

test('a refusal without a reason has no reason field', () => {
    const parts = toThreadMessages([turn({
        tools: [step({ id: 'c2', status: 'running', message: '' })],
        approvals: [approval({ decision: { kind: 'reject', reason: '' }, sent: true })],
    })])[1].content as Array<Record<string, unknown>>;

    assert.equal('reason' in (parts[0].approval as object), false);
});

test('a change restored after a reload has a tool call of its own', () => {
    const parts = toThreadMessages([turn({ user: null, status: 'awaiting', approvals: [approval({ id: 'x9' })] })])[0].content as Array<Record<string, unknown>>;

    assert.equal(parts.length, 1);
    assert.equal(parts[0].toolCallId, 'x9');
    assert.equal(parts[0].toolName, 'propose_plan');
});

test('order: tools, then the text, then the changes', () => {
    const parts = toThreadMessages([turn({
        text: 'I propose this.',
        tools: [step({ id: 'a' }), step({ id: 'c2', tool: 'propose_plan', status: 'running', message: '' })],
        approvals: [approval({})],
    })])[1].content as Array<Record<string, unknown>>;

    assert.deepEqual(parts.map((p) => p.type === 'text' ? 'text' : p.toolCallId), ['a', 'text', 'c2']);
});

test('text written after the decision comes below the change', () => {
    const parts = toThreadMessages([turn({
        text: 'I propose this.Done, it was refused.',
        resumeAt: 15,
        tools: [step({ id: 'c2', tool: 'propose_plan', status: 'running', message: '' })],
        approvals: [approval({ decision: { kind: 'reject', reason: '' }, sent: true })],
    })])[1].content as Array<Record<string, unknown>>;

    assert.deepEqual(parts.map((p) => p.type === 'text' ? p.text : p.toolCallId), ['I propose this.', 'c2', 'Done, it was refused.']);
});

test('statuses: streaming runs, failed carries its error', () => {
    assert.deepEqual(toThreadMessages([turn({ status: 'streaming' })])[1].status, { type: 'running' });
    assert.deepEqual(toThreadMessages([turn({ status: 'failed', error: 'Boom' })])[1].status, { type: 'incomplete', reason: 'error', error: 'Boom' });
});

test('a message with nothing yet has no parts', () => {
    assert.deepEqual(toThreadMessages([turn({ status: 'streaming' })])[1].content, []);
});

test('the composer waits only while the last turn has an open change', () => {
    assert.equal(hasPendingApproval([]), false);
    assert.equal(hasPendingApproval([turn({ approvals: [approval({})] })]), true);
    assert.equal(hasPendingApproval([turn({ approvals: [approval({ decision: { kind: 'approve' } })] })]), false);
    assert.equal(hasPendingApproval([turn({ approvals: [approval({})] }), turn({ id: 't2' })]), false);
});
