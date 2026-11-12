import React from 'react';

import { Button, Icon } from '../../shared/ids';
import type { EngineSummary } from '../providers';

const STATE_LABEL: Record<EngineSummary['state'], string> = {
    online: 'Online',
    offline: 'Offline',
    not_configured: 'Not configured',
    unknown: 'Checking…',
};

interface EngineBannerProps {
    summary: EngineSummary;
    siteaccess: string;
    onRefresh: () => void;
}

/**
 * What answers on the current siteaccess right now, and what to do when nothing does.
 */
export function EngineBanner({ summary, siteaccess, onRefresh }: EngineBannerProps) {
    return (
        <section className={`masilia-settings-engine masilia-settings-engine--${summary.state}`} aria-label="AI engine status">
            <div className="masilia-settings-engine__main">
                <div className="masilia-settings-engine__head">
                    <h2 className="masilia-settings-engine__title">AI engine</h2>
                    <span className="masilia-settings-chip">siteaccess: {siteaccess}</span>
                </div>
                <dl className="masilia-settings-engine__rows">
                    <div>
                        <dt>Chat</dt>
                        <dd>{summary.chat !== null ? `${summary.chat.provider.name} / ${summary.chat.model.name}` : <span className="masilia-settings-muted">Not configured</span>}</dd>
                    </div>
                    <div>
                        <dt>Image</dt>
                        <dd>{summary.image !== null ? `${summary.image.provider.name} / ${summary.image.model.name}` : <span className="masilia-settings-muted">Not configured</span>}</dd>
                    </div>
                </dl>
                {summary.state === 'offline' && summary.message !== '' ? <p className="masilia-settings-engine__text masilia-settings-engine__text--error">{summary.message}</p> : null}
                {summary.guidance !== null ? <p className="masilia-settings-engine__text">{summary.guidance}</p> : null}
            </div>
            <div className="masilia-settings-engine__status">
                <span className="masilia-settings-status" role="status">
                    <span className="masilia-settings-status__dot" aria-hidden="true" />
                    {STATE_LABEL[summary.state]}
                </span>
                <Button type="tertiary" icon="arrows-reload" ariaLabel="Check again" title="Check again" onClick={onRefresh} />
            </div>
        </section>
    );
}

export function StatusIcon({ ok }: { ok: boolean }) {
    return <Icon name={ok ? 'check-circle' : 'alert-error'} size="small" />;
}
