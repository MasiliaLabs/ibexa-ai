import React, { useMemo, useState } from 'react';

import { AdminProviders } from '@masilia-admin-kit/AdminProviders';
import { Spinner } from '@masilia-admin-kit/Spinner';
import { Tabs } from '@masilia-admin-kit/Tabs';
import { Alert } from '@masilia-admin-kit/Alert';
import { t } from '../messages';
import { describeEngine } from '../providers';
import { useSettings } from '../useSettings';
import { EngineBanner } from './EngineBanner';
import { MatrixTab } from './MatrixTab';
import { ProvidersTab } from './ProvidersTab';
import { UsageTab } from './UsageTab';

/**
 * The AI settings page: engine status for the current siteaccess, then providers, usage and the
 * siteaccess matrix.
 */
export function SettingsApp(): React.ReactElement {
    const settings = useSettings();
    const [tab, setTab] = useState('providers');
    const { snapshot, health } = settings;

    const summary = useMemo(
        () => describeEngine({ providers: snapshot.providers, models: snapshot.models, siteaccess: snapshot.currentSiteaccess, health }),
        [snapshot.providers, snapshot.models, snapshot.currentSiteaccess, health],
    );

    if (settings.loading) {
        return <p role="status" aria-live="polite"><Spinner label="Loading the AI settings…" /> Loading the AI settings…</p>;
    }

    if (settings.loadError !== null) {
        return <Alert type="error" title={settings.loadError} />;
    }

    return (
        <AdminProviders t={t}>
            <EngineBanner summary={summary} siteaccess={snapshot.currentSiteaccess} checking={settings.checking} onRefresh={() => void settings.refreshHealth()} />
            <Tabs
                label="AI settings"
                active={tab}
                onChange={setTab}
                tabs={[
                    { id: 'providers', label: 'Providers', content: <ProvidersTab settings={settings} /> },
                    { id: 'usage', label: 'Usage', content: <UsageTab types={snapshot.providerTypes} /> },
                    {
                        id: 'matrix',
                        label: 'Siteaccesses',
                        content: <MatrixTab providers={snapshot.providers} types={snapshot.providerTypes} siteaccesses={snapshot.siteaccesses} currentSiteaccess={snapshot.currentSiteaccess} />,
                    },
                ]}
            />
        </AdminProviders>
    );
}
