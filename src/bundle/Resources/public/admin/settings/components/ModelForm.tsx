import React, { useState } from 'react';

import { Button } from '../../shared/ids';
import { validateModel } from '../forms';
import type { Errors, ModelDraft } from '../forms';
import type { Provider } from '../types';
import { Dialog } from './Dialog';
import { CheckField, SelectField, TextField } from './fields';

interface ModelFormProps {
    initial: ModelDraft;
    providers: Provider[];
    /** When the model is added from a provider card, the provider is fixed. */
    fixedProvider: boolean;
    onSave: (draft: ModelDraft) => Promise<string | null>;
    onClose: () => void;
}

export function ModelForm({ initial, providers, fixedProvider, onSave, onClose }: ModelFormProps) {
    const [draft, setDraft] = useState(initial);
    const [errors, setErrors] = useState<Errors>({});
    const [serverError, setServerError] = useState<string | null>(null);
    const [saving, setSaving] = useState(false);
    const isNew = draft.id === null;
    const provider = providers.find((candidate) => candidate.id === draft.providerId);

    const patch = (changes: Partial<ModelDraft>) => setDraft((current) => ({ ...current, ...changes }));

    const submit = async () => {
        const found = validateModel(draft);
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
            title={isNew ? 'Add a model' : 'Edit model'}
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

                {fixedProvider && provider !== undefined ? (
                    <TextField label="Provider" value={provider.name} readOnly onChange={() => undefined} />
                ) : (
                    <SelectField
                        label="Provider"
                        required
                        value={draft.providerId === null ? '' : String(draft.providerId)}
                        error={errors.providerId}
                        options={[{ value: '', label: 'Choose a provider' }, ...providers.map((candidate) => ({ value: String(candidate.id), label: candidate.name }))]}
                        onChange={(value) => patch({ providerId: value === '' ? null : Number(value) })}
                    />
                )}
                <TextField label="Display name" required value={draft.name} error={errors.name} placeholder="For example: Fast model" onChange={(name) => patch({ name })} autoFocus />
                <TextField
                    label="Model identifier"
                    required
                    value={draft.identifier}
                    error={errors.identifier}
                    hint="The name the provider API expects, copied from its documentation."
                    onChange={(identifier) => patch({ identifier })}
                />
                <div className="masilia-settings-form__row">
                    <TextField label="Temperature" required type="number" step="0.1" min="0" max="2" value={draft.temperature} error={errors.temperature} hint="0 is precise, 2 is creative." onChange={(temperature) => patch({ temperature })} />
                    <TextField label="Maximum tokens" required type="number" min="1" step="1" value={draft.maxTokens} error={errors.maxTokens} hint="Length limit of an answer." onChange={(maxTokens) => patch({ maxTokens })} />
                </div>
                <CheckField label="Can generate images" checked={draft.supportsImage} hint="Check it for an image model." onChange={(supportsImage) => patch({ supportsImage })} />
            </form>
        </Dialog>
    );
}
