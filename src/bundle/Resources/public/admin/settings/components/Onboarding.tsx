import React, { useState } from 'react';

import { DropdownField, MultiDropdownField } from '@masilia-admin-kit/Dropdown';
import { TextField } from '@masilia-admin-kit/Field';
import { Button, Tag } from '@masilia-admin-kit/ids';
import { TableHeader } from '@masilia-admin-kit/Table';
import type { ProviderType } from '../types';

interface OnboardingProps {
    types: ProviderType[];
    siteaccesses: string[];
    currentSiteaccess: string;
    onFinish: (choice: { identifier: string; apiKey: string; siteaccesses: string[] }) => void;
}

const STEPS = ['Provider', 'API key', 'Siteaccess'];

/**
 * First start, nothing configured yet: three short steps, then the provider form opens pre-filled.
 */
export function Onboarding({ types, siteaccesses, currentSiteaccess, onFinish }: OnboardingProps): React.ReactElement {
    const [step, setStep] = useState(0);
    const [identifier, setIdentifier] = useState('');
    const [apiKey, setApiKey] = useState('');
    const [selected, setSelected] = useState<string[]>(currentSiteaccess !== '' ? [currentSiteaccess] : []);
    const type = types.find((candidate) => candidate.value === identifier);

    // A provider that needs no key skips the key step.
    const lastStep = 2;
    const next = (): void => setStep((current) => (current === 0 && type?.requiresApiKey === false ? 2 : current + 1));
    const back = (): void => setStep((current) => (current === 2 && type?.requiresApiKey === false ? 0 : current - 1));
    const canContinue = step === 0 ? identifier !== '' : step === 1 ? apiKey.trim() !== '' : true;

    return (
        <section aria-label="Set up your first AI provider">
            <TableHeader
                headline="Set up your first AI provider"
                notice="Three short steps. You can change everything later."
                actions={(
                    <ol className="masilia-settings-steps" aria-label="Steps">
                        {STEPS.map((label, index) => (
                            <li key={label} aria-current={index === step ? 'step' : undefined}>
                                <Tag type={index === step ? 'primary' : index < step ? 'success' : 'neutral'} size="small">{`${index + 1}. ${label}`}</Tag>
                            </li>
                        ))}
                    </ol>
                )}
            />

            {step === 0 ? (
                <DropdownField label="Provider" required placeholder="Choose a provider" value={identifier} options={types.map((option) => ({ value: option.value, label: option.label }))} onChange={setIdentifier} />
            ) : null}

            {step === 1 ? (
                <TextField label={`API key for ${type?.label ?? 'the provider'}`} type="password" autoComplete="new-password" value={apiKey} placeholder="Paste the key" autoFocus onChange={setApiKey} />
            ) : null}

            {step === 2 ? (
                <MultiDropdownField
                        label="Use it on"
                        placeholder="No siteaccess"
                        values={selected}
                        options={siteaccesses.map((name) => ({ value: name, label: name === currentSiteaccess ? `${name} (current)` : name }))}
                        onChange={setSelected}
                    />
            ) : null}

            <div className="masilia-settings-onboarding__footer">
                {step > 0 ? <Button type="tertiary" onClick={back}>Back</Button> : null}
                {step < lastStep ? (
                    <Button type="primary" disabled={!canContinue} onClick={next}>Next</Button>
                ) : (
                    <Button type="primary" onClick={() => onFinish({ identifier, apiKey, siteaccesses: selected })}>Continue</Button>
                )}
            </div>
        </section>
    );
}
