import assert from 'node:assert/strict';
import { test } from 'node:test';

import { groupThreads, whenLabel } from './threadsView';

// Local times: the grouping is by the user's calendar day.
const now = new Date(2026, 10, 13, 15, 0, 0);
const at = (day: number, hour: number, minute = 0) => new Date(2026, 10, day, hour, minute, 0).toISOString();

test('a recent conversation says how long ago, then which day', () => {
    assert.equal(whenLabel(at(13, 14, 59), now), '1 min ago');
    assert.equal(whenLabel(new Date(now.getTime() - 20 * 1000).toISOString(), now), 'Just now');
    assert.equal(whenLabel(new Date(now.getTime() - 12 * 60000).toISOString(), now), '12 min ago');
    assert.equal(whenLabel(at(13, 11, 0), now), '4 h ago');
    assert.equal(whenLabel(at(12, 23, 0), now), 'Yesterday');
    assert.equal(whenLabel(at(5, 9, 0), now), '5 Nov');
});

test('another year shows the year, and a date it cannot read gives nothing', () => {
    assert.equal(whenLabel(new Date(2025, 10, 5, 9).toISOString(), now), '5 Nov 2025');
    assert.equal(whenLabel('not a date', now), '');
});

test('conversations are grouped by calendar day, in the order given, without empty groups', () => {
    const threads = [
        { id: 'a', title: 'A', updatedAt: at(13, 14) },
        { id: 'b', title: 'B', updatedAt: at(13, 9) },
        { id: 'c', title: 'C', updatedAt: at(12, 22) },
        { id: 'd', title: 'D', updatedAt: at(1, 8) },
    ];

    assert.deepEqual(
        groupThreads(threads, now).map((group) => [group.label, group.threads.map((thread) => thread.id)]),
        [['Today', ['a', 'b']], ['Yesterday', ['c']], ['Earlier', ['d']]],
    );
    assert.deepEqual(groupThreads([threads[3]], now).map((group) => group.label), ['Earlier']);
    assert.deepEqual(groupThreads([], now), []);
});
