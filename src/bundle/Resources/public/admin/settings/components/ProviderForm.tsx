import React, { useState } from 'react';

import { Button } from '../../shared/ids';
import { validateProvider } from '../forms';
import type { Errors, ProviderDraft } from '../forms';
import { providerLabel } from '../providers';
import type { ProviderType } from '../types';
import { Dialog } from './Dialog';
import { SelectField, TextField } from './fields';
import { SiteaccessPicker } from './SiteaccessPicker';

interface ProviderFormProps {
    initial: ProviderDraft;
    types: ProviderType[];
    siteaccesses: string[];
    currentSiteaccess: string;
    /** True when the provider already stores a key: leaving the field empty keeps it. */
    hasStoredKey: boolean;
    onSave: (draft: ProviderDraft) => Promise<string | null>;
    onClose: () => void;
}

export function ProviderForm({ initial, types, siteaccesses, currentSiteaccess, hasStoredKey, onSave, onClose }: ProviderFormProps) {
    const [draft, setDraft] = useState(initial);
    const [errors, setErrors] = useState<Errors>({});
    const [serverError, setServerError] = useState<string | null>(null);
    const [saving, setSaving] = useState(false);
    const isNew = draft.id === null;
    const type = types.find((candidate) => candidate.value === draft.identifier);

    const patch = (changes: Partial<ProviderDraft>) => setDraft((current) => ({ ...current, ...changes }));

    const submit = async () => {
        const found = validateProvider(draft, type?.requiresApiKey ?? true, hasStoredKey);
        setErrors(found);
        setServerError(null);
        if (Object.keys(found).length > 0) {
            return;
        }
        setSaving(true);
        const error = await onSave(draft);
        setSaving(false);
        if (error === null) {
            onClose();
        } else {
            setServerError(error);
        }
    };

    return (
        <Dialog
            title={isNew ? 'Add a provider' : 'Edit provider'}
            variant="panel"
            onClose={() => (saving ? undefined : onClose())}
            footer={(
                <>
                    <Button type="tertiary" onClick={onClose} disabled={saving}>Cancel</Button>
                    <Button type="primary" onClick={() => void submit()} disabled={saving}>{saving ? 'Saving…' : 'Save'}</Button>
                </>
            )}
        >
            <form className="masilia-settings-form" onSubmit={(event) => { event.preventDefault(); void submit(); }} noValidate>
                {serverError !== null ? <div className="masilia-settings-alert masilia-settings-alert--error" role="alert">{serverError}</div> : null}

                <SelectField
                    label="Provider"
                    required
                    value={draft.identifier}
                    options={types.map((option) => ({ value: option.value, label: option.label }))}
                    onChange={(identifier) => patch({ identifier, name: draft.name === '' || draft.name === providerLabel(types, draft.identifier) ? providerLabel(types, identifier) : draft.name })}
                />
                <TextField label="Display name" required value={draft.name} error={errors.name} placeholder="For example: OpenAI production" onChange={(name) => patch({ name })} autoFocus />
                <TextField
                    label="API key"
                    type="password"
                    required={(type?.requiresApiKey ?? true) && !hasStoredKey}
                    value={draft.apiKey}
                    error={errors.apiKey}
                    autoComplete="new-password"
                    placeholder={hasStoredKey ? 'A key is stored' : ''}
                    hint={hasStoredKey ? 'Leave empty to keep the stored key.' : type?.requiresApiKey === false ? 'This provider does not need a key.' : undefined}
                    onChange={(apiKey) => patch({ apiKey })}
                />
                <TextField
                    label="Endpoint"
                    type="url"
                    value={draft.apiUrl}
                    error={errors.apiUrl}
                    placeholder={type?.defaultApiUrl ?? 'The provider default'}
                    hint="Only for a proxy or a self-hosted server."
                    onChange={(apiUrl) => patch({ apiUrl })}
                />
                {isNew ? (
                    <SiteaccessPicker
                        legend="Use it on"
                        siteaccesses={siteaccesses}
                        selected={draft.siteaccesses}
                        currentSiteaccess={currentSiteaccess}
                        onChange={(selected) => patch({ siteaccesses: selected })}
                    />
                ) : null}
            </form>
        </Dialog>
    );
}
