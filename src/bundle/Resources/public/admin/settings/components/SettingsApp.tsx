import React, { useMemo, useState } from 'react';

import { iconPath } from '@masilia-admin-kit/icons';
import { AssetsProvider, TranslatorProvider } from '@masilia-admin-kit/ids';
import { Spinner } from '@masilia-admin-kit/Spinner';
import { Tabs } from '@masilia-admin-kit/Tabs';
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
        return <div className="ibexa-alert ibexa-alert--error" role="alert">{settings.loadError}</div>;
    }

    return (
        <TranslatorProvider value={{ trans: (id) => t(id) }}>
            <AssetsProvider value={{ getIconPath: iconPath }}>
                <EngineBanner summary={summary} siteaccess={snapshot.currentSiteaccess} onRefresh={() => void settings.refreshHealth()} />
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
            </AssetsProvider>
        </TranslatorProvider>
    );
}
