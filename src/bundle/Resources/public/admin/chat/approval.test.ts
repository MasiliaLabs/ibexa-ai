import assert from 'node:assert/strict';
import { test } from 'node:test';

import { parseApproval } from './approval';

test('the proposal is read into a heading and labelled rows', () => {
    const summary = parseApproval(
        'Intent: create_folder\nTitle: "Test (a supprimer)"\nType: folder\nSiteaccess: fossil_exit\nParent: 1018\nLanguage: eng-GB\n\nThe change runs only if you approve it.',
    );

    assert.equal(summary.heading, 'Create a folder');
    assert.deepEqual(summary.rows, [
        { label: 'Title', value: 'Test (a supprimer)' },
        { label: 'Content type', value: 'folder' },
        { label: 'Site', value: 'fossil_exit' },
        { label: 'Parent location', value: '1018' },
        { label: 'Language', value: 'eng-GB' },
    ]);
    assert.deepEqual(summary.notes, []);
});

test('free text stays a note, and the closing sentence is dropped', () => {
    const summary = parseApproval('Intent: update_content\nContent ID: 5\nFields: title, body\nTranslate the page into French.\n\nThe change runs only if you approve it.');

    assert.equal(summary.heading, 'Update content');
    assert.deepEqual(summary.rows.map((row) => row.label), ['Content', 'Fields']);
    assert.deepEqual(summary.notes, ['Translate the page into French.']);
});

test('an intent it does not know is shown as it is; nothing to read gives an empty summary', () => {
    assert.equal(parseApproval('Intent: fly_to_mars').heading, 'fly_to_mars');
    assert.deepEqual(parseApproval(undefined), { heading: null, rows: [], notes: [] });
    assert.deepEqual(parseApproval(''), { heading: null, rows: [], notes: [] });
});
