import React from 'react';

import { Icon } from '../../shared/ids';
import { cellState, providerLabel } from '../providers';
import type { Provider, ProviderType } from '../types';

interface MatrixTabProps {
    providers: Provider[];
    types: ProviderType[];
    siteaccesses: string[];
    currentSiteaccess: string;
}

/**
 * Which provider answers on which siteaccess. Rows are siteaccesses, columns providers.
 */
export function MatrixTab({ providers, types, siteaccesses, currentSiteaccess }: MatrixTabProps) {
    if (providers.length === 0 || siteaccesses.length === 0) {
        return <p className="masilia-settings-muted">Add a provider to see where it is used.</p>;
    }

    return (
        <div>
            <div className="masilia-settings-matrix-scroll">
                <table className="masilia-settings-table masilia-settings-matrix">
                    <thead>
                        <tr>
                            <th scope="col">Siteaccess</th>
                            {providers.map((provider) => (
                                <th scope="col" key={provider.id}>
                                    {provider.name}
                                    {providerLabel(types, provider.identifier) !== provider.name ? <span className="masilia-settings-matrix__type">{providerLabel(types, provider.identifier)}</span> : null}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {siteaccesses.map((name) => (
                            <tr key={name} className={name === currentSiteaccess ? 'is-current' : undefined}>
                                <th scope="row">
                                    {name}
                                    {name === currentSiteaccess ? <span className="masilia-settings-chip">current</span> : null}
                                </th>
                                {providers.map((provider) => {
                                    const state = cellState(provider, name);

                                    return (
                                        <td key={provider.id} className={`masilia-settings-matrix__cell masilia-settings-matrix__cell--${state}`}>
                                            {state === 'ready' ? <><Icon name="check-circle" size="small" /><span className="visually-hidden">Ready</span></> : null}
                                            {state === 'no_model' ? <><Icon name="alert-warning" size="small" /><span className="visually-hidden">Assigned, no chat model</span></> : null}
                                            {state === 'unassigned' ? <span aria-hidden="true">—</span> : null}
                                            {state === 'unassigned' ? <span className="visually-hidden">Not assigned</span> : null}
                                        </td>
                                    );
                                })}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            <ul className="masilia-settings-legend">
                <li><Icon name="check-circle" size="small" /> Assigned, with a chat model</li>
                <li><Icon name="alert-warning" size="small" /> Assigned, no chat model yet</li>
                <li><span aria-hidden="true">—</span> Not assigned</li>
            </ul>
        </div>
    );
}
