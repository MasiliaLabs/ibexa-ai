import React, { useCallback, useEffect, useState } from 'react';

import { FilterCard, FilterSelect } from '@masilia-admin-kit/FilterCard';
import { Spinner } from '@masilia-admin-kit/Spinner';
import { StatTile } from '@masilia-admin-kit/StatTile';
import { DataTable, TableHeader } from '@masilia-admin-kit/Table';
import type { Column } from '@masilia-admin-kit/Table';
import { api } from '../api';
import { providerLabel } from '../providers';
import type { ProviderType, ProviderUsage, Usage } from '../types';
import { DEFAULT_WINDOW, WINDOWS, formatCount, formatLatency, formatRate, formatTokens, isEmptyWindow, requestShare } from '../usage';

interface UsageTabProps {
    types: ProviderType[];
}

export function UsageTab({ types }: UsageTabProps): React.ReactElement {
    const [usage, setUsage] = useState<Usage | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [loading, setLoading] = useState(true);
    const [windowKey, setWindowKey] = useState(DEFAULT_WINDOW);

    const load = useCallback(async () => {
        setLoading(true);
        const result = await api.usage();
        if (result.ok) {
            setUsage(result.data);
            setError(null);
        } else {
            setError(result.message);
        }
        setLoading(false);
    }, []);

    useEffect(() => {
        void load();
    }, [load]);

    if (usage === null) {
        return error !== null
            ? <div className="ibexa-alert ibexa-alert--error" role="alert">{error}</div>
            : <p role="status"><Spinner label="Loading the usage…" /> Loading the usage…</p>;
    }

    const current = usage.windows[windowKey] ?? usage.windows[DEFAULT_WINDOW];
    const totals = current.totals;

    const columns: Column<ProviderUsage>[] = [
        { key: 'provider', header: 'Provider', render: (row) => <strong>{providerLabel(types, row.providerIdentifier)}</strong> },
        {
            key: 'share',
            header: 'Share of requests',
            render: (row) => {
                const share = requestShare(row, totals);

                return (
                    <>
                        <span className="masilia-settings-bar" aria-hidden="true"><span style={{ width: `${share}%` }} /></span>
                        <span className="masilia-settings-bar__label">{share}%</span>
                    </>
                );
            },
        },
        { key: 'requests', header: 'Requests', render: (row) => formatCount(row.total) },
        { key: 'success', header: 'Success', render: (row) => formatRate(row.success, row.total) },
        { key: 'errors', header: 'Errors', render: (row) => formatCount(row.error) },
        { key: 'latency', header: 'Latency', render: (row) => formatLatency(row.avgLatencyMs) },
    ];

    return (
        <div aria-busy={loading}>
            <FilterCard
                actions={(
                    <button type="button" className="btn ibexa-btn ibexa-btn--secondary ibexa-adaptive-filters__submit-btn" disabled={loading} onClick={() => void load()}>
                        {loading ? 'Refreshing…' : 'Refresh'}
                    </button>
                )}
            >
                <FilterSelect label="Period" value={windowKey} options={WINDOWS.map((option) => ({ value: option.key, label: option.label }))} onChange={setWindowKey} />
            </FilterCard>
            {error !== null ? <div className="ibexa-alert ibexa-alert--error" role="alert">{error}</div> : null}

            <div className="masilia-kit-stats">
                <StatTile value={formatCount(totals.total)} label="Requests" />
                <StatTile value={formatRate(totals.success, totals.total)} label="Success rate" />
                <StatTile value={formatCount(totals.error)} label="Errors" />
                <StatTile value={formatLatency(totals.avgLatencyMs)} label="Average latency" />
                <StatTile value={formatTokens(totals.tokensIn)} label="Tokens in" />
                <StatTile value={formatTokens(totals.tokensOut)} label="Tokens out" />
            </div>

            <TableHeader headline="By provider" count={isEmptyWindow(totals) ? 0 : current.perProvider.length} />
            <DataTable
                columns={columns}
                rows={isEmptyWindow(totals) ? [] : current.perProvider}
                rowKey={(row) => row.providerIdentifier}
                empty={{ info: 'No request was recorded in this period.' }}
                label="Usage by provider"
            />
        </div>
    );
}
