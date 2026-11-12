import React, { useId } from 'react';

import { Button, Icon } from '../../shared/ids';
import { modelOptionLabel, modelsOf, providerLabel } from '../providers';
import type { Model, Provider, ProviderType, TestResult } from '../types';
import { StatusIcon } from './EngineBanner';
import { SelectField } from './fields';
import { ModelRow } from './ModelRow';
import { SiteaccessPicker } from './SiteaccessPicker';

interface ProviderCardProps {
    provider: Provider;
    models: Model[];
    types: ProviderType[];
    siteaccesses: string[];
    currentSiteaccess: string;
    expanded: boolean;
    testing: boolean;
    testResult: TestResult | null;
    onToggle: () => void;
    onTest: () => void;
    onEdit: () => void;
    onDelete: () => void;
    onSetSiteaccesses: (selected: string[]) => void;
    onSetChatModel: (modelId: number | null) => void;
    onSetImageModel: (modelId: number | null) => void;
    onAddModel: () => void;
    onEditModel: (model: Model) => void;
    onDeleteModel: (model: Model) => void;
}

export function ProviderCard(props: ProviderCardProps) {
    const { provider, models, types, expanded, testing, testResult } = props;
    const bodyId = useId();
    const own = modelsOf(models, provider.id);
    const imageModels = own.filter((model) => model.supportsImage);
    const serves = provider.siteaccesses.includes(props.currentSiteaccess);
    const noModel = own.length === 0;

    return (
        <li className={`masilia-settings-card${serves ? ' masilia-settings-card--current' : ''}`}>
            <div className="masilia-settings-card__header">
                <button type="button" className="masilia-settings-card__toggle" aria-expanded={expanded} aria-controls={bodyId} onClick={props.onToggle}>
                    <span className={`masilia-settings-card__chevron${expanded ? ' masilia-settings-card__chevron--open' : ''}`} aria-hidden="true">
                        <Icon name="arrow-chevron-down" size="small" />
                    </span>
                    <span className="masilia-settings-card__info">
                        <span className="masilia-settings-card__name">{provider.name}</span>
                        <span className="masilia-settings-card__meta">
                            {providerLabel(types, provider.identifier) !== provider.name ? <span className="masilia-settings-tag">{providerLabel(types, provider.identifier)}</span> : null}
                            <span>{provider.siteaccesses.length > 0 ? provider.siteaccesses.join(', ') : 'Not used on any siteaccess'}</span>
                            <span>{own.length} {own.length === 1 ? 'model' : 'models'}</span>
                            {provider.apiKey === null && types.find((type) => type.value === provider.identifier)?.requiresApiKey !== false ? <span className="masilia-settings-warn">No API key</span> : null}
                        </span>
                    </span>
                </button>
                <div className="masilia-settings-card__actions">
                    <Button type="secondary" size="small" disabled={testing} onClick={props.onTest}>{testing ? 'Testing…' : 'Test'}</Button>
                    <Button type="tertiary" size="small" icon="edit" ariaLabel={`Edit ${provider.name}`} title="Edit" onClick={props.onEdit} />
                    <Button type="tertiary" size="small" icon="trash" ariaLabel={`Delete ${provider.name}`} title="Delete" onClick={props.onDelete} />
                </div>
            </div>

            {testing ? <p className="masilia-settings-test" role="status">Testing the connection…</p> : null}
            {testResult !== null ? (
                <p className={`masilia-settings-test masilia-settings-test--${testResult.success ? 'ok' : 'fail'}`} role="status">
                    <StatusIcon ok={testResult.success} />
                    <span>
                        {testResult.message}
                        {testResult.streamTested && testResult.streamOk === false ? ' Streaming did not work.' : ''}
                    </span>
                </p>
            ) : null}

            {expanded ? (
                <div className="masilia-settings-card__body" id={bodyId}>
                    <dl className="masilia-settings-card__details">
                        <div><dt>Endpoint</dt><dd>{provider.apiUrl ?? types.find((type) => type.value === provider.identifier)?.defaultApiUrl ?? 'Provider default'}</dd></div>
                        <div><dt>API key</dt><dd>{provider.apiKey !== null ? 'Stored' : 'Not set'}</dd></div>
                    </dl>

                    <SiteaccessPicker
                        legend="Used on these siteaccesses"
                        siteaccesses={props.siteaccesses}
                        selected={provider.siteaccesses}
                        currentSiteaccess={props.currentSiteaccess}
                        onChange={props.onSetSiteaccesses}
                    />

                    <div className="masilia-settings-form__row">
                        <SelectField
                            label="Chat model"
                            value={provider.activeChatModelId === null ? '' : String(provider.activeChatModelId)}
                            disabled={noModel}
                            options={[{ value: '', label: 'None' }, ...own.map((model) => ({ value: String(model.id), label: modelOptionLabel(model) }))]}
                            onChange={(value) => props.onSetChatModel(value === '' ? null : Number(value))}
                        />
                        <SelectField
                            label="Image model"
                            value={provider.activeImageModelId === null ? '' : String(provider.activeImageModelId)}
                            disabled={imageModels.length === 0}
                            hint={imageModels.length === 0 && !noModel ? 'No model of this provider can generate images.' : undefined}
                            options={[{ value: '', label: 'None' }, ...imageModels.map((model) => ({ value: String(model.id), label: modelOptionLabel(model) }))]}
                            onChange={(value) => props.onSetImageModel(value === '' ? null : Number(value))}
                        />
                    </div>

                    <div className="masilia-settings-models">
                        <div className="masilia-settings-models__head">
                            <h3>Models ({own.length})</h3>
                            <Button type="secondary" size="small" icon="add" onClick={props.onAddModel}>Add a model</Button>
                        </div>
                        {noModel ? (
                            <p className="masilia-settings-muted">This provider has no model yet. Add one to use it.</p>
                        ) : (
                            <ul className="masilia-settings-models__list">
                                {own.map((model) => (
                                    <ModelRow
                                        key={model.id}
                                        model={model}
                                        chatActive={model.id === provider.activeChatModelId}
                                        imageActive={model.id === provider.activeImageModelId}
                                        onEdit={props.onEditModel}
                                        onDelete={props.onDeleteModel}
                                    />
                                ))}
                            </ul>
                        )}
                    </div>
                </div>
            ) : null}
        </li>
    );
}
