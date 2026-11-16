import assert from 'node:assert/strict';
import { test } from 'node:test';

import { cleanName, encodeMentions, insertMention, labelFor, mentionHref, mentionTrigger, splitMentions, tokenFor } from './mentions';
import type { Picked } from './mentions';

const atouts: Picked = { contentId: 1353, locationId: 1343, name: 'The strengths of our new product range', label: 'The strengths of our new' };
const about: Picked = { contentId: 9, locationId: null, name: 'About', label: 'About' };

test('an "@" that starts a word at the caret opens a mention, with what follows as the query', () => {
    assert.deepEqual(mentionTrigger('Translate @atou', 15), { start: 10, end: 15, query: 'atou' });
    assert.deepEqual(mentionTrigger('@', 1), { start: 0, end: 1, query: '' });
    assert.deepEqual(mentionTrigger('Hello (@abo', 11), { start: 7, end: 11, query: 'abo' });
});

test('an "@" inside a word, an e-mail, or behind the caret opens nothing', () => {
    assert.equal(mentionTrigger('write to me@example.com', 23), null);
    assert.equal(mentionTrigger('Translate @atouts please', 24), null);
    assert.equal(mentionTrigger('no mention here', 15), null);
    assert.equal(mentionTrigger('Translate @atou', 5), null);
});

test('choosing a content puts its label after the "@" and the caret after it', () => {
    const trigger = mentionTrigger('Translate @atou to French', 15);

    assert.ok(trigger);
    const result = insertMention('Translate @atou to French', trigger, 'Les atouts');

    assert.equal(result.text, 'Translate @Les atouts  to French');
    assert.equal(result.caret, 'Translate @Les atouts '.length);
});

test('the label is the name, short; the same label for two contents is told apart by the id', () => {
    assert.equal(labelFor('The strengths of our new product range', 1353, []), 'The strengths of our new');
    assert.equal(labelFor('The strengths of our new product lines', 1355, [atouts]), 'The strengths of our new #1355');
    assert.equal(labelFor('The strengths of our new product range', 1353, [atouts]), 'The strengths of our new');
    assert.equal(labelFor('[()]', 7, []), 'content 7');
});

test('a name cannot end the token early', () => {
    assert.equal(cleanName('Hero [v2] (draft)\nfinal'), 'Hero v2 draft final');
    assert.equal(tokenFor({ contentId: 5, locationId: 6, name: 'A [b] c' }), '@[A b c](content:5/6)');
    assert.equal(tokenFor({ contentId: 5, locationId: null, name: 'Plain' }), '@[Plain](content:5)');
});

test('on sending, each picked "@label" still in the text becomes its token', () => {
    const text = encodeMentions('Translate @The strengths of our new into French, and check @About.', [atouts, about]);

    assert.equal(text, 'Translate @[The strengths of our new product range](content:1353/1343) into French, and check @[About](content:9).');
});

test('a label the user edited, deleted, or that is part of a longer word is left alone', () => {
    assert.equal(encodeMentions('Mail me@About and @Aboutness', [about]), 'Mail me@About and @Aboutness');
    assert.equal(encodeMentions('Nothing picked is nothing changed', [about]), 'Nothing picked is nothing changed');
    assert.equal(encodeMentions('Same text', []), 'Same text');
});

test('the longest label wins when one starts with another, and special characters are not patterns', () => {
    const short: Picked = { contentId: 1, locationId: null, name: 'Home', label: 'Home' };
    const long: Picked = { contentId: 2, locationId: null, name: 'Home (2040)', label: 'Home 2040' };
    const odd: Picked = { contentId: 3, locationId: null, name: 'Q&A + FAQ?', label: 'Q&A + FAQ?' };

    assert.equal(encodeMentions('@Home 2040 and @Home and @Q&A + FAQ?', [short, long, odd]), '@[Home 2040](content:2) and @[Home](content:1) and @[Q&A + FAQ?](content:3)');
});

test('a sent message is cut into text and mentions, to show the mentions as links', () => {
    assert.deepEqual(splitMentions('See @[About](content:9) and @[Les atouts](content:1353/1343).'), [
        { type: 'text', text: 'See ' },
        { type: 'mention', name: 'About', contentId: 9, locationId: null },
        { type: 'text', text: ' and ' },
        { type: 'mention', name: 'Les atouts', contentId: 1353, locationId: 1343 },
        { type: 'text', text: '.' },
    ]);
    assert.deepEqual(splitMentions('No mention'), [{ type: 'text', text: 'No mention' }]);
    assert.deepEqual(splitMentions('@[not closed](content:'), [{ type: 'text', text: '@[not closed](content:' }]);
});

test('a mention opens the content, at its location when known', () => {
    assert.equal(mentionHref({ contentId: 9, locationId: null }), '/view/content/9');
    assert.equal(mentionHref({ contentId: 1353, locationId: 1343 }), '/view/content/1353/full/true/1343');
});
