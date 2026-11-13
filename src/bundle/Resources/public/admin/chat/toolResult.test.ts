import assert from 'node:assert/strict';
import { test } from 'node:test';

import { toolOutcome } from './toolResult';

test('a tool JSON answer is read as it is', () => {
    assert.deepEqual(toolOutcome('{"success":true,"message":"Created","data":{"content_id":12}}'), { ok: true, message: 'Created', href: null });
});

test('a failed tool is not ok, and says why', () => {
    assert.deepEqual(toolOutcome('{"success":false,"message":"Not found"}'), { ok: false, message: 'Not found', href: null });
});

test('an answer without a success flag is not a success', () => {
    assert.equal(toolOutcome('{"message":"?"}').ok, false);
});

test('a rejected call is not reported as a success', () => {
    const outcome = toolOutcome('TOOL NOT EXECUTED. The user rejected this action.');

    assert.equal(outcome.ok, false);
    assert.match(outcome.message, /rejected/);
});

test('plain text is an ok message and long text is clipped', () => {
    assert.deepEqual(toolOutcome('Done.'), { ok: true, message: 'Done.', href: null });

    const long = toolOutcome('a'.repeat(900));
    assert.equal(long.ok, true);
    assert.equal(long.message.length, 501);
    assert.ok(long.message.endsWith('…'));
});

test('JSON that is not an object is plain text', () => {
    assert.deepEqual(toolOutcome('[1,2]'), { ok: true, message: '[1,2]', href: null });
    assert.deepEqual(toolOutcome('12'), { ok: true, message: '12', href: null });
});

test('an already parsed answer reads the same', () => {
    assert.deepEqual(toolOutcome({ success: true, message: 'Ok' }), { ok: true, message: 'Ok', href: null });
    assert.deepEqual(toolOutcome(undefined), { ok: true, message: '', href: null });
});

test('a created content can be opened from the card', () => {
    const answer = JSON.stringify({ success: true, message: 'Created', data: { content_id: 1359, location_id: 1349 } });

    assert.equal(toolOutcome(answer).href, '/view/content/1359/full/true/1349');
    assert.equal(toolOutcome({ success: true, message: 'x', data: { content_id: 5, location_id: 6 } }).href, '/view/content/5/full/true/6');
});

test('there is no link without both ids, on a failure, or with anything but whole numbers', () => {
    assert.equal(toolOutcome(JSON.stringify({ success: true, message: 'ok', data: { content_id: 1 } })).href, null);
    assert.equal(toolOutcome(JSON.stringify({ success: false, message: 'no', data: { content_id: 1, location_id: 2 } })).href, null);
    assert.equal(toolOutcome(JSON.stringify({ success: true, message: 'x', data: { content_id: '1/../x', location_id: 2 } })).href, null);
    assert.equal(toolOutcome('plain text').href, null);
});
