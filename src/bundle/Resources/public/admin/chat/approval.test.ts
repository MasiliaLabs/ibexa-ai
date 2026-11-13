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
    assert.deepEqual(parseApproval(undefined), { heading: null, rows: [], notes: [], risk: null });
    assert.deepEqual(parseApproval(''), { heading: null, rows: [], notes: [], risk: null });
});

test('adding an image is read like any other change', () => {
    const summary = parseApproval('Intent: add_image\nTitle: "Logo"\nParent: 1020\nImage: attached by you\n\nThe change runs only if you approve it.');

    assert.equal(summary.heading, 'Add an image to the media library');
    assert.deepEqual(summary.rows, [
        { label: 'Title', value: 'Logo' },
        { label: 'Parent location', value: '1020' },
        { label: 'Image', value: 'attached by you' },
    ]);
});

test('a capability of the AI OS gives its own heading, its risk and its own rows', () => {
    const summary = parseApproval('Action: Empty the trash\nRisk: destructive\nItems: 12\nNote: These contents are deleted for good. This cannot be undone.\n\nThe change runs only if you approve it.');

    assert.equal(summary.heading, 'Empty the trash');
    assert.equal(summary.risk, 'destructive');
    assert.deepEqual(summary.rows, [{ label: 'Items', value: '12' }]);
    assert.deepEqual(summary.notes, ['These contents are deleted for good. This cannot be undone.']);
});

test('rows of a capability keep their labels, and an unknown risk is none', () => {
    const summary = parseApproval('Action: Update content\nRisk: write\nContent: Home (5)\nLanguage: its own language\nFields: title, body');

    assert.equal(summary.risk, 'write');
    assert.deepEqual(summary.rows.map((row) => row.label), ['Content', 'Language', 'Fields']);
    assert.equal(parseApproval('Action: X\nRisk: weird').risk, null);
});

test('a note is a note even when it holds a colon, and a label may hold a number', () => {
    const summary = parseApproval('Action: Apply 2 changes\nRisk: write\nStep 1: Create a folder: Title X\nStep 2: content.update: content_id: result of f.id\nNote: The steps are applied together: if one fails, none is kept.');

    assert.deepEqual(summary.rows.map((row) => row.label), ['Step 1', 'Step 2']);
    assert.equal(summary.rows[0].value, 'Create a folder: Title X');
    assert.deepEqual(summary.notes, ['The steps are applied together: if one fails, none is kept.']);
});
