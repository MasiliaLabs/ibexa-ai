import assert from 'node:assert/strict';
import { test } from 'node:test';

import { formatCount, formatLatency, formatRate, formatTokens, isEmptyWindow, requestShare } from './usage';

const totals = { total: 200, success: 190, error: 10, tokensIn: 0, tokensOut: 0, avgLatencyMs: 0 };
const row = (total: number) => ({ providerIdentifier: 'x', total, success: total, error: 0, tokensIn: 0, tokensOut: 0, avgLatencyMs: 0 });

test('rates are whole percentages, with a dash when there is nothing to measure', () => {
    assert.equal(formatRate(190, 200), '95%');
    assert.equal(formatRate(1, 3), '33%');
    assert.equal(formatRate(0, 0), '—');
});

test('latency and counts', () => {
    assert.equal(formatLatency(0), '—');
    assert.equal(formatLatency(1234), '1,234 ms');
    assert.equal(formatCount(1234567), '1,234,567');
});

test('token counts are compact', () => {
    assert.equal(formatTokens(0), '0');
    assert.equal(formatTokens(999), '999');
    assert.equal(formatTokens(1200), '1.2k');
    assert.equal(formatTokens(2000), '2k');
    assert.equal(formatTokens(15400), '15k');
    assert.equal(formatTokens(3_400_000), '3.4M');
});

test('the share of a provider is its part of the window total', () => {
    assert.equal(requestShare(row(50), totals), 25);
    assert.equal(requestShare(row(0), { ...totals, total: 0 }), 0);
    assert.equal(isEmptyWindow({ ...totals, total: 0 }), true);
    assert.equal(isEmptyWindow(totals), false);
});
