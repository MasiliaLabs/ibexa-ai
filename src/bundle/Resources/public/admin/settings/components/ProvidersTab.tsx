import React, { useEffect, useMemo, useState } from 'react';

import { Button, Icon } from '../../shared/ids';
import { emptyModelDraft, emptyProviderDraft, modelToDraft, providerToDraft } from '../forms';
import type { ModelDraft, ProviderDraft } from '../forms';
import { providerLabel, searchProviders } from '../providers';
import type { SettingsController } from '../useSettings';
import type { Model, Provider } from '../types';
import { ConfirmDialog } from './ConfirmDialog';
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

export function ProvidersTab({ settings }: ProvidersTabProps) {
    const { snapshot } = settings;
    const { providers, models, providerTypes, siteaccesses, currentSiteaccess } = snapshot;
    const [query, setQuery] = useState('');
    const [expanded, setExpanded] = useState<Set<number>>(new Set());
    const [editing, setEditing] = useState<Editing>(null);
    const [pending, setPending] = useState<Pending | null>(null);

    const found = useMemo(() => searchProviders(providers, models, providerTypes, query), [providers, models, providerTypes, query]);

    // A search that matches a model opens its provider.
    useEffect(() => {
        if (found.matchingProviderIds.size === 0) {
            return;
        }
        setExpanded((current) => new Set([...current, ...found.matchingProviderIds]));
    }, [found.matchingProviderIds]);

    const toggle = (id: number) => setExpanded((current) => {
        const next = new Set(current);
        if (next.has(id)) {
            next.delete(id);
        } else {
            next.add(id);
        }

        return next;
    });

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
        <div>
            <div className="masilia-settings-toolbar">
                <div className="masilia-settings-search">
                    <Icon name="search" size="small" />
                    <input
                        type="search"
                        className="masilia-settings-input"
                        placeholder="Search providers and models"
                        aria-label="Search providers and models"
                        value={query}
                        onChange={(event) => setQuery(event.target.value)}
                    />
                </div>
                <div className="masilia-settings-toolbar__actions">
                    {hasProviders ? <Button type="secondary" onClick={() => addModel(null)}>Add a model</Button> : null}
                    <Button type="primary" icon="add" onClick={() => setEditing({ kind: 'provider', draft: emptyProviderDraft(providerTypes[0]?.value ?? 'openai', currentSiteaccess !== '' ? [currentSiteaccess] : []), hasStoredKey: false })}>
                        Add a provider
                    </Button>
                </div>
            </div>

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
                <div className="masilia-settings-empty">
                    <p>No provider or model matches “{query}”.</p>
                    <Button type="tertiary" onClick={() => setQuery('')}>Clear the search</Button>
                </div>
            ) : (
                <ul className="masilia-settings-cards">
                    {found.providers.map((provider) => (
                        <ProviderCard
                            key={provider.id}
                            provider={provider}
                            models={models}
                            types={providerTypes}
                            siteaccesses={siteaccesses}
                            currentSiteaccess={currentSiteaccess}
                            expanded={expanded.has(provider.id)}
                            testing={settings.testing === provider.id}
                            testResult={settings.tests[provider.id] ?? null}
                            onToggle={() => toggle(provider.id)}
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
                    ))}
                </ul>
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
                    description={pending.description}
                    confirmLabel={pending.confirmLabel}
                    onCancel={() => setPending(null)}
                    onConfirm={() => {
                        const run = pending.run;
                        setPending(null);
                        run();
                    }}
                />
            ) : null}
        </div>
    );
}
