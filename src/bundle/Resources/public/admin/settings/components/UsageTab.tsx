import React, { useCallback, useEffect, useState } from 'react';

import { api } from '../api';
import { AltRadiosListField, Button } from '../../shared/ids';
import { providerLabel } from '../providers';
import type { ProviderType, Usage } from '../types';
import { DEFAULT_WINDOW, WINDOWS, formatCount, formatLatency, formatRate, formatTokens, isEmptyWindow, requestShare } from '../usage';

interface UsageTabProps {
    types: ProviderType[];
}

export function UsageTab({ types }: UsageTabProps) {
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
            ? <div className="masilia-settings-alert masilia-settings-alert--error" role="alert">{error}</div>
            : <p className="masilia-settings-muted" role="status">Loading the usage…</p>;
    }

    const current = usage.windows[windowKey] ?? usage.windows[DEFAULT_WINDOW];
    const totals = current.totals;

    return (
        <div aria-busy={loading}>
            <div className="masilia-settings-toolbar">
                <AltRadiosListField
                    id="masilia-usage-window"
                    name="masilia-usage-window"
                    label="Period"
                    direction="horizontal"
                    items={WINDOWS.map((option) => ({ id: `usage-${option.key}`, value: option.key, label: option.label }))}
                    value={windowKey}
                    onChange={setWindowKey}
                />
                <Button type="tertiary" icon="arrows-reload" disabled={loading} onClick={() => void load()}>{loading ? 'Refreshing…' : 'Refresh'}</Button>
            </div>
            {error !== null ? <div className="masilia-settings-alert masilia-settings-alert--error" role="alert">{error}</div> : null}

            <dl className="masilia-settings-stats">
                <Stat label="Requests" value={formatCount(totals.total)} />
                <Stat label="Success rate" value={formatRate(totals.success, totals.total)} />
                <Stat label="Errors" value={formatCount(totals.error)} tone={totals.error > 0 ? 'warn' : undefined} />
                <Stat label="Average latency" value={formatLatency(totals.avgLatencyMs)} />
                <Stat label="Tokens in" value={formatTokens(totals.tokensIn)} title={formatCount(totals.tokensIn)} />
                <Stat label="Tokens out" value={formatTokens(totals.tokensOut)} title={formatCount(totals.tokensOut)} />
            </dl>

            <h3 className="masilia-settings-section">By provider</h3>
            {isEmptyWindow(totals) ? (
                <p className="masilia-settings-muted">No request was recorded in this period.</p>
            ) : (
                <table className="masilia-settings-table">
                    <thead>
                        <tr>
                            <th scope="col">Provider</th>
                            <th scope="col">Share of requests</th>
                            <th scope="col" className="is-number">Requests</th>
                            <th scope="col" className="is-number">Success</th>
                            <th scope="col" className="is-number">Errors</th>
                            <th scope="col" className="is-number">Latency</th>
                        </tr>
                    </thead>
                    <tbody>
                        {current.perProvider.map((row) => {
                            const share = requestShare(row, totals);

                            return (
                                <tr key={row.providerIdentifier}>
                                    <th scope="row">{providerLabel(types, row.providerIdentifier)}</th>
                                    <td>
                                        <span className="masilia-settings-bar" aria-hidden="true"><span style={{ width: `${share}%` }} /></span>
                                        <span className="masilia-settings-bar__label">{share}%</span>
                                    </td>
                                    <td className="is-number">{formatCount(row.total)}</td>
                                    <td className="is-number">{formatRate(row.success, row.total)}</td>
                                    <td className="is-number">{formatCount(row.error)}</td>
                                    <td className="is-number">{formatLatency(row.avgLatencyMs)}</td>
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
            )}
        </div>
    );
}

function Stat({ label, value, title, tone }: { label: string; value: string; title?: string; tone?: 'warn' }) {
    return (
        <div className={`masilia-settings-stat${tone === 'warn' ? ' masilia-settings-stat--warn' : ''}`}>
            <dt>{label}</dt>
            <dd title={title}>{value}</dd>
        </div>
    );
}
