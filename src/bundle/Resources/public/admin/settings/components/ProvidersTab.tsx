import React, { useMemo, useState } from 'react';

import { ConfirmDialog } from '@masilia-admin-kit/ConfirmDialog';
import { FilterCard } from '@masilia-admin-kit/FilterCard';
import { SearchInput } from '@masilia-admin-kit/SearchInput';
import { DataTable, TableHeader } from '@masilia-admin-kit/Table';
import { emptyModelDraft, emptyProviderDraft, modelToDraft, providerToDraft } from '../forms';
import type { ModelDraft, ProviderDraft } from '../forms';
import { providerLabel, searchProviders } from '../providers';
import type { SettingsController } from '../useSettings';
import type { Model, Provider } from '../types';
import { ModelForm } from './ModelForm';
import { Onboarding } from './Onboarding';
import { ProviderCard } from './ProviderCard';
import { ProviderForm } from './ProviderForm';

interface ProvidersTabProps {
    settings: SettingsController;
}

type Editing =
    | { kind: 'provider'; draft: ProviderDraft; hasStoredKey: boolean }
    | { kind: 'model'; draft: ModelDraft; fixedProvider: boolean }
    | null;

interface Pending {
    title: string;
    description: string;
    confirmLabel: string;
    run: () => void;
}

export function ProvidersTab({ settings }: ProvidersTabProps): React.ReactElement {
    const { snapshot } = settings;
    const { providers, models, providerTypes, siteaccesses, currentSiteaccess } = snapshot;
    const [query, setQuery] = useState('');
    const [editing, setEditing] = useState<Editing>(null);
    const [pending, setPending] = useState<Pending | null>(null);

    const found = useMemo(() => searchProviders(providers, models, providerTypes, query), [providers, models, providerTypes, query]);

    const askDeleteProvider = (provider: Provider) => {
        const count = models.filter((model) => model.providerId === provider.id).length;
        setPending({
            title: `Delete ${provider.name}?`,
            description: count > 0
                ? `This removes the provider and its ${count} ${count === 1 ? 'model' : 'models'}. It cannot be undone.`
                : 'This removes the provider. It cannot be undone.',
            confirmLabel: 'Delete',
            run: () => void settings.deleteProvider(provider.id),
        });
    };

    const askDeleteModel = (model: Model) => setPending({
        title: `Delete ${model.name}?`,
        description: 'This removes the model. It cannot be undone.',
        confirmLabel: 'Delete',
        run: () => void settings.deleteModel(model.id),
    });

    const editProvider = (provider: Provider) => setEditing({ kind: 'provider', draft: providerToDraft(provider), hasStoredKey: provider.apiKey !== null });

    const addModel = (providerId: number | null) => setEditing({ kind: 'model', draft: emptyModelDraft(providerId), fixedProvider: providerId !== null });

    const hasProviders = providers.length > 0;

    return (
        <>
            <FilterCard
                search={<SearchInput value={query} onChange={setQuery} placeholder="Search providers and models" clearLabel="Clear the search" />}
                actions={(
                    <>
                        {hasProviders ? <button type="button" className="btn ibexa-btn ibexa-btn--secondary ibexa-adaptive-filters__submit-btn" onClick={() => addModel(null)}>Add a model</button> : null}
                        <button
                            type="button"
                            className="btn ibexa-btn ibexa-btn--primary ibexa-adaptive-filters__submit-btn"
                            onClick={() => setEditing({ kind: 'provider', draft: emptyProviderDraft(providerTypes[0]?.value ?? 'openai', currentSiteaccess !== '' ? [currentSiteaccess] : []), hasStoredKey: false })}
                        >
                            Add a provider
                        </button>
                    </>
                )}
            />
            <TableHeader headline="Providers" count={found.providers.length} />

            {!hasProviders ? (
                <Onboarding
                    types={providerTypes}
                    siteaccesses={siteaccesses}
                    currentSiteaccess={currentSiteaccess}
                    onFinish={(choice) => setEditing({
                        kind: 'provider',
                        hasStoredKey: false,
                        draft: { ...emptyProviderDraft(choice.identifier, choice.siteaccesses), name: providerLabel(providerTypes, choice.identifier), apiKey: choice.apiKey },
                    })}
                />
            ) : found.providers.length === 0 ? (
                <DataTable
                    columns={[]}
                    rows={[]}
                    rowKey={() => ''}
                    empty={{ info: `No provider or model matches “${query}”.`, action: <button type="button" className="btn ibexa-btn ibexa-btn--ghost" onClick={() => setQuery('')}>Clear the search</button> }}
                    label="Providers"
                />
            ) : (
                found.providers.map((provider) => (
                    <ProviderCard
                        key={provider.id}
                        provider={provider}
                        models={models}
                        types={providerTypes}
                        siteaccesses={siteaccesses}
                        currentSiteaccess={currentSiteaccess}
                        open={found.matchingProviderIds.has(provider.id)}
                        testing={settings.testing === provider.id}
                        testResult={settings.tests[provider.id] ?? null}
                        onTest={() => void settings.testProvider(provider.id)}
                        onEdit={() => editProvider(provider)}
                        onDelete={() => askDeleteProvider(provider)}
                        onSetSiteaccesses={(selected) => void settings.setSiteaccesses(provider.id, selected)}
                        onSetChatModel={(modelId) => void settings.setChatModel(provider.id, modelId)}
                        onSetImageModel={(modelId) => void settings.setImageModel(provider.id, modelId)}
                        onAddModel={() => addModel(provider.id)}
                        onEditModel={(model) => setEditing({ kind: 'model', draft: modelToDraft(model), fixedProvider: true })}
                        onDeleteModel={askDeleteModel}
                    />
                ))
            )}

            {editing?.kind === 'provider' ? (
                <ProviderForm
                    initial={editing.draft}
                    types={providerTypes}
                    siteaccesses={siteaccesses}
                    currentSiteaccess={currentSiteaccess}
                    hasStoredKey={editing.hasStoredKey}
                    onSave={settings.saveProvider}
                    onClose={() => setEditing(null)}
                />
            ) : null}
            {editing?.kind === 'model' ? (
                <ModelForm
                    initial={editing.draft}
                    providers={providers}
                    fixedProvider={editing.fixedProvider}
                    onSave={settings.saveModel}
                    onClose={() => setEditing(null)}
                />
            ) : null}
            {pending !== null ? (
                <ConfirmDialog
                    title={pending.title}
                    confirmLabel={pending.confirmLabel}
                    cancelLabel="Cancel"
                    danger
                    onCancel={() => setPending(null)}
                    onConfirm={() => {
                        const run = pending.run;
                        setPending(null);
                        run();
                    }}
                >
                    {pending.description}
                </ConfirmDialog>
            ) : null}
        </>
    );
}
