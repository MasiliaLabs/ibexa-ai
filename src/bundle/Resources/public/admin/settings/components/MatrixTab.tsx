import React from 'react';

import { IconSvg } from '@masilia-admin-kit/IconSvg';
import { DataTable, TableHeader } from '@masilia-admin-kit/Table';
import type { Column } from '@masilia-admin-kit/Table';
import { Tag } from '@masilia-admin-kit/ids';
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
export function MatrixTab({ providers, types, siteaccesses, currentSiteaccess }: MatrixTabProps): React.ReactElement {
    const columns: Column<string>[] = [
        {
            key: 'siteaccess',
            header: 'Siteaccess',
            render: (name) => (
                <>
                    <strong>{name}</strong> {name === currentSiteaccess ? <Tag type="primary" size="small">current</Tag> : null}
                </>
            ),
        },
        ...providers.map((provider): Column<string> => ({
            key: `provider-${provider.id}`,
            header: (
                <>
                    {provider.name}
                    {providerLabel(types, provider.identifier) !== provider.name ? <span className="masilia-settings-muted"> ({providerLabel(types, provider.identifier)})</span> : null}
                </>
            ),
            render: (name) => {
                const state = cellState(provider, name);

                if (state === 'ready') {
                    return <><IconSvg name="check-circle" size="small" /><span className="visually-hidden">Ready</span></>;
                }
                if (state === 'no_model') {
                    return <><IconSvg name="alert-warning" size="small" /><span className="visually-hidden">Assigned, no chat model yet</span></>;
                }

                return <><span aria-hidden="true">—</span><span className="visually-hidden">Not assigned</span></>;
            },
        })),
    ];

    return (
        <>
            <TableHeader headline="Providers by siteaccess" count={siteaccesses.length} notice="Assigned with a chat model: check · assigned without one: warning · not assigned: dash" />
            <DataTable columns={columns} rows={providers.length === 0 ? [] : siteaccesses} rowKey={(name) => name} empty={{ info: 'Add a provider to see where it is used.' }} label="Providers by siteaccess" />
        </>
    );
}
