import React, { useMemo, useState } from 'react';

import { AssetsProvider } from '../../shared/ids';
import { iconPath } from '../../shared/icons';
import { describeEngine } from '../providers';
import { useSettings } from '../useSettings';
import { EngineBanner } from './EngineBanner';
import { MatrixTab } from './MatrixTab';
import { ProvidersTab } from './ProvidersTab';
import { Tabs } from './Tabs';
import { UsageTab } from './UsageTab';

/**
 * The AI settings page: engine status for the current siteaccess, then providers, usage and the
 * siteaccess matrix.
 */
export function SettingsApp() {
    const settings = useSettings();
    const [tab, setTab] = useState('providers');
    const { snapshot, health } = settings;

    const summary = useMemo(
        () => describeEngine({ providers: snapshot.providers, models: snapshot.models, siteaccess: snapshot.currentSiteaccess, health }),
        [snapshot.providers, snapshot.models, snapshot.currentSiteaccess, health],
    );

    if (settings.loading) {
        return <p className="masilia-settings-muted" role="status" aria-live="polite">Loading the AI settings…</p>;
    }

    if (settings.loadError !== null) {
        return (
            <div className="masilia-settings-alert masilia-settings-alert--error" role="alert">
                {settings.loadError}
            </div>
        );
    }

    return (
        <AssetsProvider value={{ getIconPath: iconPath }}>
            <div className="masilia-settings">
                <EngineBanner summary={summary} siteaccess={snapshot.currentSiteaccess} onRefresh={() => void settings.refreshHealth()} />
                <Tabs
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
            </div>
        </AssetsProvider>
    );
}
