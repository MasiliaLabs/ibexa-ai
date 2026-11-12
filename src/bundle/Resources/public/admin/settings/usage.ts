import type { ProviderUsage, UsageTotals } from './types';

export const WINDOWS: ReadonlyArray<{ key: string; label: string }> = [
    { key: '24h', label: 'Last 24 hours' },
    { key: '7d', label: 'Last 7 days' },
    { key: '30d', label: 'Last 30 days' },
];

export const DEFAULT_WINDOW = '7d';

export function formatCount(value: number): string {
    return value.toLocaleString('en-US');
}

export function formatLatency(ms: number): string {
    return ms > 0 ? `${formatCount(ms)} ms` : '—';
}

/** Whole-number percentage, or a dash when there is nothing to divide by. */
export function formatRate(part: number, total: number): string {
    return total > 0 ? `${Math.round((part / total) * 100)}%` : '—';
}

/** Compact token counts: 1 200 -> 1.2k, 3 400 000 -> 3.4M. */
export function formatTokens(value: number): string {
    if (value >= 1_000_000) {
        return `${trimZero(value / 1_000_000)}M`;
    }
    if (value >= 10_000) {
        return `${Math.round(value / 1_000)}k`;
    }
    if (value >= 1_000) {
        return `${trimZero(value / 1_000)}k`;
    }

    return String(value);
}

function trimZero(value: number): string {
    return value.toFixed(1).replace(/\.0$/, '');
}

/** Share of the window's requests that went to a provider, 0..100, for the bar. */
export function requestShare(row: ProviderUsage, totals: UsageTotals): number {
    return totals.total > 0 ? Math.round((row.total / totals.total) * 100) : 0;
}

export function isEmptyWindow(totals: UsageTotals): boolean {
    return totals.total === 0;
}
