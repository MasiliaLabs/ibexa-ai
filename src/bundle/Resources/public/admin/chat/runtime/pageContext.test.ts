import assert from 'node:assert/strict';
import { test } from 'node:test';

import { runContext } from './pageContext';

test('the content view names the content and the location the editor has open', () => {
    const [entry] = runContext('/view/content/1353/full/true/1343');

    assert.equal(entry.description, 'masilia.page');
    assert.deepEqual(JSON.parse(entry.value), { contentId: 1353, locationId: 1343 });
});

test('anywhere else the agent is told nothing', () => {
    assert.deepEqual(runContext('/admin/ai/settings'), []);
    assert.deepEqual(runContext('/view/content/abc/full/true/1'), []);
    assert.deepEqual(runContext('/view/content/1/full/true/2/extra'), []);
    assert.deepEqual(runContext(''), []);
});
