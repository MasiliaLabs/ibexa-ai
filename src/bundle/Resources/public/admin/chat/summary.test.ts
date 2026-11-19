import assert from 'node:assert/strict';
import { test } from 'node:test';

import { parseSummary } from './summary';

test('a message that starts with the summary heading is a summary', () => {
    const summary = parseSummary('## Previous conversation summary:\n\n**Context**\nSetting up a site.');

    assert.equal(summary?.title, 'Previous conversation summary');
    assert.equal(summary?.body, '**Context**\nSetting up a site.');
});

test('the heading level and the colon do not matter', () => {
    assert.ok(parseSummary('# Previous conversation summary\nText'));
    assert.ok(parseSummary('### Conversation summary:\nText'));
});

test('an ordinary message, or a summary with nothing in it, is not one', () => {
    assert.equal(parseSummary('List the children of the home page'), null);
    assert.equal(parseSummary('Please write a previous conversation summary'), null);
    assert.equal(parseSummary('## Previous conversation summary:\n   '), null);
});
