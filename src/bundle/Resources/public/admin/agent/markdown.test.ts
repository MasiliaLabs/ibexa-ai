import { test } from 'node:test';
import assert from 'node:assert/strict';

import { parseInline, parseMarkdown } from './markdown';

test('paragraphs are separated by blank lines and keep their line breaks', () => {
    assert.deepEqual(parseMarkdown('One\ntwo\n\nThree'), [
        { type: 'paragraph', children: [{ type: 'text', text: 'One\ntwo' }] },
        { type: 'paragraph', children: [{ type: 'text', text: 'Three' }] },
    ]);
});

test('headings carry their level', () => {
    assert.deepEqual(parseMarkdown('## What I can do'), [
        { type: 'heading', level: 2, children: [{ type: 'text', text: 'What I can do' }] },
    ]);
});

test('bullet and numbered lists group their consecutive items', () => {
    const blocks = parseMarkdown('- one\n- two\n\n1. first\n2. second');

    assert.deepEqual(blocks, [
        { type: 'list', ordered: false, items: [[{ type: 'text', text: 'one' }], [{ type: 'text', text: 'two' }]] },
        { type: 'list', ordered: true, items: [[{ type: 'text', text: 'first' }], [{ type: 'text', text: 'second' }]] },
    ]);
});

test('list items read inline formatting', () => {
    const [list] = parseMarkdown('- Content ID : **1328**');

    assert.deepEqual(list, {
        type: 'list',
        ordered: false,
        items: [[{ type: 'text', text: 'Content ID : ' }, { type: 'strong', children: [{ type: 'text', text: '1328' }] }]],
    });
});

test('fenced code is kept verbatim, closed or still streaming', () => {
    assert.deepEqual(parseMarkdown('```json\n{"a": **1**}\n```\nAfter'), [
        { type: 'code', text: '{"a": **1**}' },
        { type: 'paragraph', children: [{ type: 'text', text: 'After' }] },
    ]);
    assert.deepEqual(parseMarkdown('```\nline one\nline two'), [{ type: 'code', text: 'line one\nline two' }]);
});

test('a line of dashes is a rule', () => {
    assert.deepEqual(parseMarkdown('Above\n\n---\n\nBelow')[1], { type: 'rule' });
});

test('inline bold, emphasis and code are read', () => {
    assert.deepEqual(parseInline('Le dossier **« Test IA »** (type `folder`) est *créé*.'), [
        { type: 'text', text: 'Le dossier ' },
        { type: 'strong', children: [{ type: 'text', text: '« Test IA »' }] },
        { type: 'text', text: ' (type ' },
        { type: 'code', text: 'folder' },
        { type: 'text', text: ') est ' },
        { type: 'em', children: [{ type: 'text', text: 'créé' }] },
        { type: 'text', text: '.' },
    ]);
});

test('formatting marks inside code are not interpreted', () => {
    assert.deepEqual(parseInline('`**not bold**`'), [{ type: 'code', text: '**not bold**' }]);
});

test('unfinished markers stay as text while a message is streaming', () => {
    assert.deepEqual(parseInline('This is **not finished'), [{ type: 'text', text: 'This is **not finished' }]);
    assert.deepEqual(parseInline('an open `code'), [{ type: 'text', text: 'an open `code' }]);
});

test('identifiers and arithmetic are left alone', () => {
    assert.deepEqual(parseInline('fossil_exit_fr and 2 * 3 * 4'), [{ type: 'text', text: 'fossil_exit_fr and 2 * 3 * 4' }]);
});

test('web addresses and site paths become links', () => {
    assert.deepEqual(parseInline('See [the page](https://example.org/a) or [admin](/admin/x).'), [
        { type: 'text', text: 'See ' },
        { type: 'link', href: 'https://example.org/a', children: [{ type: 'text', text: 'the page' }] },
        { type: 'text', text: ' or ' },
        { type: 'link', href: '/admin/x', children: [{ type: 'text', text: 'admin' }] },
        { type: 'text', text: '.' },
    ]);
});

test('other link targets are never turned into links', () => {
    for (const target of ['javascript:alert(1)', 'data:text/html,x', '//evil.example/x']) {
        assert.deepEqual(parseInline(`[click](${target})`), [{ type: 'text', text: `[click](${target})` }]);
    }
});
