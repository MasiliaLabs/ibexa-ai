import React, { useState } from 'react';

import { Button } from '../../shared/ids';
import type { ProviderType } from '../types';
import { TextField } from './fields';
import { SiteaccessPicker } from './SiteaccessPicker';

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
export function Onboarding({ types, siteaccesses, currentSiteaccess, onFinish }: OnboardingProps) {
    const [step, setStep] = useState(0);
    const [identifier, setIdentifier] = useState('');
    const [apiKey, setApiKey] = useState('');
    const [selected, setSelected] = useState<string[]>(currentSiteaccess !== '' ? [currentSiteaccess] : []);
    const type = types.find((candidate) => candidate.value === identifier);

    // A provider that needs no key skips the key step.
    const lastStep = 2;
    const next = () => setStep((current) => (current === 0 && type?.requiresApiKey === false ? 2 : current + 1));
    const back = () => setStep((current) => (current === 2 && type?.requiresApiKey === false ? 0 : current - 1));
    const canContinue = step === 0 ? identifier !== '' : step === 1 ? apiKey.trim() !== '' : true;

    return (
        <section className="masilia-settings-onboarding" aria-labelledby="masilia-settings-onboarding-title">
            <h2 id="masilia-settings-onboarding-title">Set up your first AI provider</h2>
            <p className="masilia-settings-muted">Three short steps. You can change everything later.</p>

            <ol className="masilia-settings-steps" aria-label="Steps">
                {STEPS.map((label, index) => (
                    <li key={label} className={index === step ? 'is-current' : index < step ? 'is-done' : ''} aria-current={index === step ? 'step' : undefined}>
                        <span className="masilia-settings-steps__number">{index + 1}</span>
                        {label}
                    </li>
                ))}
            </ol>

            {step === 0 ? (
                <fieldset className="masilia-settings-choices">
                    <legend className="visually-hidden">Choose a provider</legend>
                    {types.map((option) => (
                        <button
                            key={option.value}
                            type="button"
                            className={`masilia-settings-choice${identifier === option.value ? ' is-selected' : ''}`}
                            aria-pressed={identifier === option.value}
                            onClick={() => setIdentifier(option.value)}
                        >
                            {option.label}
                        </button>
                    ))}
                </fieldset>
            ) : null}

            {step === 1 ? (
                <TextField label={`API key for ${type?.label ?? 'the provider'}`} type="password" autoComplete="new-password" value={apiKey} placeholder="Paste the key" autoFocus onChange={setApiKey} />
            ) : null}

            {step === 2 ? (
                <SiteaccessPicker legend="Use it on" siteaccesses={siteaccesses} selected={selected} currentSiteaccess={currentSiteaccess} onChange={setSelected} />
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
