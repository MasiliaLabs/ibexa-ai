import assert from 'node:assert/strict';
import { test } from 'node:test';

import { NO_FILTERS, countCapabilities, filterDomains, needsOf, riskTagType, risksOf, startsOpen } from './filters';
import type { CapabilityDto, DomainDto } from './types';

const cap = (name: string, risk: CapabilityDto['risk'], usable = true, summary = ''): CapabilityDto => ({ name, summary, risk, package: 'p', usable, denial: '', requires: null });
const domain = (name: string, capabilities: CapabilityDto[], enabled = true): DomainDto => ({ name, enabled, packages: ['p'], usable: 0, risks: {}, capabilities });

const domains = [
    domain('content', [cap('content.read', 'read', true, 'Reads a content'), cap('content.delete', 'destructive', false, 'Deletes a content')]),
    domain('access', [cap('role.assign', 'access', false)]),
];

test('no filter keeps everything', () => {
    assert.equal(countCapabilities(filterDomains(domains, NO_FILTERS)), 3);
});

test('words must all be found in the name, the summary or the risk', () => {
    assert.deepEqual(filterDomains(domains, { ...NO_FILTERS, query: 'deletes CONTENT' })[0].capabilities.map((c) => c.name), ['content.delete']);
    assert.equal(filterDomains(domains, { ...NO_FILTERS, query: 'destructive' }).length, 1);
});

test('risk, domain and availability narrow down, and an empty domain disappears', () => {
    assert.equal(filterDomains(domains, { ...NO_FILTERS, risk: 'access' }).map((d) => d.name).join(), 'access');
    assert.equal(filterDomains(domains, { ...NO_FILTERS, domain: 'content' }).length, 1);
    assert.deepEqual(filterDomains(domains, { ...NO_FILTERS, availability: 'usable' }).flatMap((d) => d.capabilities.map((c) => c.name)), ['content.read']);
    assert.equal(filterDomains(domains, { ...NO_FILTERS, query: 'nothing like this' }).length, 0);
});

test('a domain starts open when on (except access) or when searching', () => {
    assert.equal(startsOpen(domains[0], NO_FILTERS), true);
    assert.equal(startsOpen(domains[1], NO_FILTERS), false);
    assert.equal(startsOpen(domains[1], { ...NO_FILTERS, query: 'x' }), true);
    assert.equal(startsOpen(domain('off', [], false), NO_FILTERS), false);
});

test('risk tags, needs and the risks present', () => {
    assert.equal(riskTagType('read'), 'info');
    assert.equal(riskTagType('write'), 'warning');
    assert.equal(riskTagType('destructive'), 'error');
    assert.equal(riskTagType('access'), 'primary-alt');
    assert.equal(needsOf(null), '—');
    assert.equal(needsOf('content/read'), 'content/read');
    assert.equal(needsOf(['a', 'b']), 'a, b');
    assert.deepEqual(risksOf(domains), ['read', 'destructive', 'access']);
});
