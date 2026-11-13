import assert from 'node:assert/strict';
import { test } from 'node:test';

import { collectSources } from './sources';

const answer = (data: unknown) => JSON.stringify({ success: true, message: 'ok', data });
const call = (data: unknown) => ({ type: 'tool-call', result: answer(data) });
const text = (value: string) => ({ type: 'text', text: value });

test('a content the answer cites by name or by id becomes a source, in the order of the answer', () => {
    const sources = collectSources([
        call({ items: [{ content_id: 1358, content_type: 'cta', name: 'Rejoignez la transition' }, { content_id: 1355, content_type: 'hero_banner', name: 'Les atouts' }] }),
        text('The page has a hero (1355) and the call to action "Rejoignez la transition".'),
    ]);

    assert.deepEqual(sources.map((source) => source.contentId), [1355, 1358]);
    assert.equal(sources[1].type, 'cta');
    assert.equal(sources[0].href, '/view/content/1355');
});

test('what the answer does not talk about is not listed, however much the tool returned', () => {
    const items = Array.from({ length: 200 }, (_, i) => ({ content_id: 5000 + i, content_type: 'paragraph', name: `Paragraph number ${i}` }));

    assert.deepEqual(collectSources([call({ items }), text('Nothing to report.')]), []);
});

test('an id is cited as a number of its own, not inside another number', () => {
    const sources = collectSources([call({ items: [{ content_id: 13, name: 'Short' }] }), text('Content 1358 and 213 are fine.')]);

    assert.deepEqual(sources, []);
});

test('a short name does not cite a content: it appears in ordinary sentences', () => {
    assert.deepEqual(collectSources([call({ items: [{ content_id: 9, name: 'About' }] }), text('About the page: it is fine.')]), []);
    assert.equal(collectSources([call({ items: [{ content_id: 9, name: 'About' }] }), text('See content 9.')]).length, 1);
});

test('the site tree is searched at any depth, and a location gives the full address', () => {
    const tree = { siteStructure: { children: [{ location_id: 1343, content_id: 1353, name: 'Les atouts de la sortie', children: [{ location_id: 1345, content_id: 1355, name: 'Hero banner block' }] }] } };
    const sources = collectSources([call(tree), text('Read "Hero banner block" and content 1353.')]);

    // In the order of the answer: the block is named before the page's id.
    assert.deepEqual(sources.map((source) => source.href), ['/view/content/1355/full/true/1345', '/view/content/1353/full/true/1343']);
});

test('a content seen twice is listed once, with the location when one of the answers had it', () => {
    const sources = collectSources([
        call({ results: [{ content_id: 7, name: 'Seven pages' }] }),
        call({ children: [{ content_id: 7, location_id: 70, name: 'Seven pages' }] }),
        text('See content 7.'),
    ]);

    assert.equal(sources.length, 1);
    assert.equal(sources[0].href, '/view/content/7/full/true/70');
});

test('a failed call and a call that was not run give nothing', () => {
    const failed = { type: 'tool-call', result: JSON.stringify({ success: false, message: 'no', data: { items: [{ content_id: 3, name: 'Hidden thing' }] } }) };
    const skipped = { type: 'tool-call', result: 'TOOL NOT EXECUTED: rejected' };

    assert.deepEqual(collectSources([failed, skipped, text('Hidden thing, content 3.')]), []);
});

test('no more than eight sources, and nothing without an answer', () => {
    const items = Array.from({ length: 20 }, (_, i) => ({ content_id: 100 + i, name: `Content item ${i}` }));
    const answerText = items.map((item) => `content ${item.content_id}`).join(', ');

    assert.equal(collectSources([call({ items }), text(answerText)]).length, 8);
    assert.deepEqual(collectSources([call({ items })]), []);
});

test('a name several contents share cannot cite one of them, but their id can', () => {
    const folders = [{ content_id: 20, name: 'Images folder' }, { content_id: 21, name: 'Images folder' }, { content_id: 22, name: 'Fossil Exit Media' }];

    assert.deepEqual(collectSources([call({ items: folders }), text('It is in the Images folder of Fossil Exit Media.')]).map((source) => source.contentId), [22]);
    assert.deepEqual(collectSources([call({ items: folders }), text('It is in the Images folder (content 21).')]).map((source) => source.contentId), [21]);
});
