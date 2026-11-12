import assert from 'node:assert/strict';
import { test } from 'node:test';

import { toolOutcome } from './toolResult';

test('a tool JSON answer is read as it is', () => {
    assert.deepEqual(toolOutcome('{"success":true,"message":"Created","data":{"content_id":12}}'), { ok: true, message: 'Created' });
});

test('a failed tool is not ok, and says why', () => {
    assert.deepEqual(toolOutcome('{"success":false,"message":"Not found"}'), { ok: false, message: 'Not found' });
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
    assert.deepEqual(toolOutcome('Done.'), { ok: true, message: 'Done.' });

    const long = toolOutcome('a'.repeat(900));
    assert.equal(long.ok, true);
    assert.equal(long.message.length, 501);
    assert.ok(long.message.endsWith('…'));
});

test('JSON that is not an object is plain text', () => {
    assert.deepEqual(toolOutcome('[1,2]'), { ok: true, message: '[1,2]' });
    assert.deepEqual(toolOutcome('12'), { ok: true, message: '12' });
});

test('an already parsed answer reads the same', () => {
    assert.deepEqual(toolOutcome({ success: true, message: 'Ok' }), { ok: true, message: 'Ok' });
    assert.deepEqual(toolOutcome(undefined), { ok: true, message: '' });
});
