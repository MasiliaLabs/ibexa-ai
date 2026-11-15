import React from 'react';

import type { TagType } from '@masilia-admin-kit/ids';
import { IconButton } from '@masilia-admin-kit/IconButton';
import { IconSvg } from '@masilia-admin-kit/IconSvg';
import { Notice } from '@masilia-admin-kit/Notice';
import { StatTile } from '@masilia-admin-kit/StatTile';
import { TableHeader } from '@masilia-admin-kit/Table';
import { Tag } from '@masilia-admin-kit/ids';
import type { EngineSummary } from '../providers';

const STATE_LABEL: Record<EngineSummary['state'], string> = {
    online: 'Online',
    offline: 'Offline',
    not_configured: 'Not configured',
    unknown: 'Checking…',
};

const STATE_TAG: Record<EngineSummary['state'], TagType> = {
    online: 'success',
    offline: 'error',
    not_configured: 'warning',
    unknown: 'neutral',
};

interface EngineBannerProps {
    summary: EngineSummary;
    siteaccess: string;
    onRefresh: () => void;
}

/**
 * What answers on the current siteaccess right now, and what to do when nothing does.
 */
export function EngineBanner({ summary, siteaccess, onRefresh }: EngineBannerProps): React.ReactElement {
    const answer = (active: EngineSummary['chat']): string => (active !== null ? `${active.provider.name} / ${active.model.name}` : 'None');

    return (
        <section aria-label="AI engine status">
            <TableHeader
                headline="AI engine"
                notice={`Siteaccess: ${siteaccess}`}
                actions={(
                    <>
                        <span role="status"><Tag type={STATE_TAG[summary.state]} size="small">{STATE_LABEL[summary.state]}</Tag></span>
                        <IconButton icon="arrows-reload" label="Check again" onClick={onRefresh} />
                    </>
                )}
            />
            <div className="masilia-kit-stats">
                <StatTile value={answer(summary.chat)} label="Chat" />
                <StatTile value={answer(summary.image)} label="Image" />
            </div>
            {summary.state === 'offline' && summary.message !== '' ? <div className="ibexa-alert ibexa-alert--error" role="alert">{summary.message}</div> : null}
            {summary.guidance !== null ? <Notice>{summary.guidance}</Notice> : null}
        </section>
    );
}

export function StatusIcon({ ok }: { ok: boolean }): React.ReactElement {
    return <IconSvg name={ok ? 'check-circle' : 'alert-error'} size="small" />;
}
