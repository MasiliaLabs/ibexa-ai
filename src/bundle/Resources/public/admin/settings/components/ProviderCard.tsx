import React from 'react';

import { DropdownField, MultiDropdownField } from '@masilia-admin-kit/Dropdown';
import { IconButton } from '@masilia-admin-kit/IconButton';
import { Button, Tag } from '@masilia-admin-kit/ids';
import { Notice } from '@masilia-admin-kit/Notice';
import { Section } from '@masilia-admin-kit/Section';
import { DataTable, TableHeader } from '@masilia-admin-kit/Table';
import type { Column } from '@masilia-admin-kit/Table';
import { Alert } from '@masilia-admin-kit/Alert';
import { modelOptionLabel, modelsOf, providerLabel } from '../providers';
import type { Model, Provider, ProviderType, TestResult } from '../types';

interface ProviderCardProps {
    provider: Provider;
    models: Model[];
    types: ProviderType[];
    siteaccesses: string[];
    currentSiteaccess: string;
    /** The search found something in this provider: its section opens */
    open: boolean;
    testing: boolean;
    testResult: TestResult | null;
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

function ProviderTitle({ provider, types, count, serves }: { provider: Provider; types: ProviderType[]; count: number; serves: boolean }): React.ReactElement {
    const type = types.find((candidate) => candidate.value === provider.identifier);

    return (
        <span className="masilia-settings-title">
            <strong>{provider.name}</strong>
            {providerLabel(types, provider.identifier) !== provider.name ? <Tag type="neutral" size="small">{providerLabel(types, provider.identifier)}</Tag> : null}
            {serves ? <Tag type="success" size="small">Current siteaccess</Tag> : null}
            {provider.apiKey === null && type?.requiresApiKey !== false ? <Tag type="warning" size="small">No API key</Tag> : null}
            <span className="masilia-settings-muted">
                {provider.siteaccesses.length > 0 ? provider.siteaccesses.join(', ') : 'Not used on any siteaccess'} · {count} {count === 1 ? 'model' : 'models'}
            </span>
        </span>
    );
}

/** A provider: its summary as the title of a section, its settings, its models and what it answers for inside. */
export function ProviderCard(props: ProviderCardProps): React.ReactElement {
    const { provider, models, types, testing, testResult } = props;
    const own = modelsOf(models, provider.id);
    const imageModels = own.filter((model) => model.supportsImage);
    const serves = provider.siteaccesses.includes(props.currentSiteaccess);
    const noModel = own.length === 0;

    const columns: Column<Model>[] = [
        {
            key: 'name',
            header: 'Model',
            render: (model) => (
                <>
                    <strong>{model.name}</strong>{' '}
                    {model.id === provider.activeChatModelId ? <Tag type="primary" size="small">Chat</Tag> : null}{' '}
                    {model.id === provider.activeImageModelId ? <Tag type="primary-alt" size="small">Image</Tag> : null}{' '}
                    {model.supportsImage && model.id !== provider.activeImageModelId ? <Tag type="neutral" size="small">Image capable</Tag> : null}{' '}
                    {model.supportsVision ? <Tag type="neutral" size="small">Vision</Tag> : null}
                </>
            ),
        },
        { key: 'identifier', header: 'Identifier', render: (model) => <code>{model.identifier}</code> },
        { key: 'temperature', header: 'Temperature', render: (model) => model.temperature },
        { key: 'tokens', header: 'Tokens', render: (model) => model.maxTokens.toLocaleString('en-US') },
        {
            key: 'actions',
            header: <span className="visually-hidden">Actions</span>,
            actions: true,
            render: (model) => (
                <>
                    <IconButton icon="edit" label={`Edit ${model.name}`} onClick={() => props.onEditModel(model)} />
                    <IconButton icon="trash" label={`Delete ${model.name}`} onClick={() => props.onDeleteModel(model)} />
                </>
            ),
        },
    ];

    return (
        <Section title={<ProviderTitle provider={provider} types={types} count={own.length} serves={serves} />} open={props.open}>
            <TableHeader
                headline="Settings"
                plain
                notice={`Endpoint: ${provider.apiUrl ?? types.find((type) => type.value === provider.identifier)?.defaultApiUrl ?? 'provider default'} · API key: ${provider.apiKey !== null ? 'stored' : 'not set'}`}
                actions={(
                    <>
                        <Button type="secondary" size="small" disabled={testing} onClick={props.onTest}>{testing ? 'Testing…' : 'Test'}</Button>
                        <IconButton icon="edit" label={`Edit ${provider.name}`} onClick={props.onEdit} />
                        <IconButton icon="trash" label={`Delete ${provider.name}`} onClick={props.onDelete} />
                    </>
                )}
            />
            {testing ? <Notice role="status">Testing the connection…</Notice> : null}
            {testResult !== null ? (
                <Alert type={testResult.success ? 'success' : 'error'} role="status" title={<>{testResult.message}{testResult.streamTested && testResult.streamOk === false ? ' Streaming did not work.' : ''}</>} />
            ) : null}

            <div className="masilia-kit-row">
                <MultiDropdownField
                    label="Used on these siteaccesses"
                    placeholder="No siteaccess"
                    hint="A siteaccess uses one provider: choosing it here takes it away from the others."
                    values={provider.siteaccesses}
                    options={props.siteaccesses.map((name) => ({ value: name, label: name === props.currentSiteaccess ? `${name} (current)` : name }))}
                    onChange={props.onSetSiteaccesses}
                />
                <DropdownField
                    label="Chat model"
                    value={provider.activeChatModelId === null ? '' : String(provider.activeChatModelId)}
                    disabled={noModel}
                    emptyLabel="None"
                    options={own.map((model) => ({ value: String(model.id), label: modelOptionLabel(model) }))}
                    onChange={(value) => props.onSetChatModel(value === '' ? null : Number(value))}
                />
                <DropdownField
                    label="Image model"
                    value={provider.activeImageModelId === null ? '' : String(provider.activeImageModelId)}
                    disabled={imageModels.length === 0}
                    hint={imageModels.length === 0 && !noModel ? 'No model of this provider can generate images.' : undefined}
                    emptyLabel="None"
                    options={imageModels.map((model) => ({ value: String(model.id), label: modelOptionLabel(model) }))}
                    onChange={(value) => props.onSetImageModel(value === '' ? null : Number(value))}
                />
            </div>

            <TableHeader headline="Models" count={own.length} actions={<Button type="secondary" size="small" icon="add" onClick={props.onAddModel}>Add a model</Button>} />
            <DataTable columns={columns} rows={own} rowKey={(model) => String(model.id)} empty={{ info: 'This provider has no model yet. Add one to use it.' }} label={`Models of ${provider.name}`} />
        </Section>
    );
}
