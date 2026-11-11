import { test } from 'node:test';
import assert from 'node:assert/strict';

import { readAgentEvents, splitFrames, toAgentEvent } from './protocol';

test('splitFrames returns complete frames and keeps the unfinished tail', () => {
    const { frames, rest } = splitFrames(
        'event: text.delta\ndata: {"v":1,"text":"a"}\n\nevent: text.delta\ndata: {"v":1,"te',
    );

    assert.equal(frames.length, 1);
    assert.equal(frames[0].event, 'text.delta');
    assert.equal(rest, 'event: text.delta\ndata: {"v":1,"te');
});

test('splitFrames accepts CRLF line endings', () => {
    const { frames } = splitFrames('event: run.completed\r\ndata: {"v":1,"interrupted":false}\r\n\r\n');

    assert.equal(frames.length, 1);
    assert.equal(frames[0].event, 'run.completed');
});

test('toAgentEvent reads each known event and rejects other versions', () => {
    assert.deepEqual(toAgentEvent({ event: 'text.delta', data: '{"v":1,"text":"Hi"}' }), { type: 'text.delta', text: 'Hi' });
    assert.deepEqual(toAgentEvent({ event: 'run.completed', data: '{"v":1,"interrupted":true}' }), {
        type: 'run.completed',
        interrupted: true,
    });
    assert.equal(toAgentEvent({ event: 'text.delta', data: '{"v":2,"text":"Hi"}' }), null);
    assert.equal(toAgentEvent({ event: 'unknown.event', data: '{"v":1}' }), null);
    assert.equal(toAgentEvent({ event: 'text.delta', data: 'not json' }), null);
});

test('toAgentEvent drops events missing their required fields', () => {
    assert.equal(toAgentEvent({ event: 'tool.started', data: '{"v":1,"tool":"explore_site"}' }), null);
    assert.equal(toAgentEvent({ event: 'approval.requested', data: '{"v":1,"id":"x"}' }), null);
});

test('toAgentEvent gives a safe default message to run.error', () => {
    assert.deepEqual(toAgentEvent({ event: 'run.error', data: '{"v":1}' }), {
        type: 'run.error',
        code: 'error',
        message: 'The assistant failed.',
    });
});

test('readAgentEvents yields events even when a frame is split across chunks', async () => {
    const encoder = new TextEncoder();
    const chunks = [
        'event: text.delta\ndata: {"v":1,"te',
        'xt":"Hel"}\n\nevent: text.delta\ndata: {"v":1,"text":"lo"}\n\n',
        'event: run.completed\ndata: {"v":1,"interrupted":false}\n\n',
    ];
    const body = new ReadableStream<Uint8Array>({
        start(controller) {
            for (const chunk of chunks) {
                controller.enqueue(encoder.encode(chunk));
            }
            controller.close();
        },
    });

    const events = [];
    for await (const event of readAgentEvents(body)) {
        events.push(event);
    }

    assert.deepEqual(events, [
        { type: 'text.delta', text: 'Hel' },
        { type: 'text.delta', text: 'lo' },
        { type: 'run.completed', interrupted: false },
    ]);
});

test('readAgentEvents keeps multi-byte characters split across chunks', async () => {
    const bytes = new TextEncoder().encode('event: text.delta\ndata: {"v":1,"text":"é"}\n\n');
    const cut = bytes.indexOf(0xc3) + 1; // between the two bytes of "é"
    const body = new ReadableStream<Uint8Array>({
        start(controller) {
            controller.enqueue(bytes.slice(0, cut));
            controller.enqueue(bytes.slice(cut));
            controller.close();
        },
    });

    const events = [];
    for await (const event of readAgentEvents(body)) {
        events.push(event);
    }

    assert.deepEqual(events, [{ type: 'text.delta', text: 'é' }]);
});
