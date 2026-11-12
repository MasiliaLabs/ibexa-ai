import React, { useId } from 'react';
import type { ReactNode } from 'react';

interface FieldFrameProps {
    id: string;
    label: string;
    required?: boolean;
    hint?: string;
    error?: string;
    children: ReactNode;
}

function FieldFrame({ id, label, required, hint, error, children }: FieldFrameProps) {
    return (
        <div className="masilia-settings-field">
            <label className="ids-label masilia-settings-field__label" htmlFor={id}>
                {label}
                {required === true ? <span aria-hidden="true"> *</span> : null}
            </label>
            {children}
            {error !== undefined ? <p className="masilia-settings-field__error" id={`${id}-error`}>{error}</p> : null}
            {hint !== undefined ? <p className="masilia-settings-field__hint" id={`${id}-hint`}>{hint}</p> : null}
        </div>
    );
}

function describedBy(id: string, hint?: string, error?: string): string | undefined {
    const ids = [error !== undefined ? `${id}-error` : '', hint !== undefined ? `${id}-hint` : ''].filter((value) => value !== '');

    return ids.length > 0 ? ids.join(' ') : undefined;
}

interface TextFieldProps {
    label: string;
    value: string;
    onChange: (value: string) => void;
    type?: 'text' | 'password' | 'url' | 'number';
    required?: boolean;
    hint?: string;
    error?: string;
    placeholder?: string;
    autoComplete?: string;
    step?: string;
    min?: string;
    max?: string;
    readOnly?: boolean;
    autoFocus?: boolean;
}

export function TextField({ label, value, onChange, type = 'text', required, hint, error, placeholder, autoComplete, step, min, max, readOnly, autoFocus }: TextFieldProps) {
    const id = useId();

    return (
        <FieldFrame id={id} label={label} required={required} hint={hint} error={error}>
            <input
                id={id}
                className="masilia-settings-input"
                type={type}
                value={value}
                placeholder={placeholder}
                autoComplete={autoComplete}
                step={step}
                min={min}
                max={max}
                readOnly={readOnly}
                data-autofocus={autoFocus === true ? '' : undefined}
                aria-invalid={error !== undefined}
                aria-required={required}
                aria-describedby={describedBy(id, hint, error)}
                onChange={(event) => onChange(event.target.value)}
            />
        </FieldFrame>
    );
}

export interface Option {
    value: string;
    label: string;
}

interface SelectFieldProps {
    label: string;
    value: string;
    options: Option[];
    onChange: (value: string) => void;
    required?: boolean;
    hint?: string;
    error?: string;
    disabled?: boolean;
}

export function SelectField({ label, value, options, onChange, required, hint, error, disabled }: SelectFieldProps) {
    const id = useId();

    return (
        <FieldFrame id={id} label={label} required={required} hint={hint} error={error}>
            <select
                id={id}
                className="masilia-settings-input masilia-settings-select"
                value={value}
                disabled={disabled}
                aria-invalid={error !== undefined}
                aria-describedby={describedBy(id, hint, error)}
                onChange={(event) => onChange(event.target.value)}
            >
                {options.map((option) => (
                    <option key={option.value} value={option.value}>{option.label}</option>
                ))}
            </select>
        </FieldFrame>
    );
}

interface CheckFieldProps {
    label: string;
    checked: boolean;
    onChange: (checked: boolean) => void;
    hint?: string;
}

export function CheckField({ label, checked, onChange, hint }: CheckFieldProps) {
    const id = useId();

    return (
        <div className="masilia-settings-field">
            <label className="masilia-settings-check" htmlFor={id}>
                <input id={id} type="checkbox" checked={checked} aria-describedby={hint !== undefined ? `${id}-hint` : undefined} onChange={(event) => onChange(event.target.checked)} />
                <span>{label}</span>
            </label>
            {hint !== undefined ? <p className="masilia-settings-field__hint" id={`${id}-hint`}>{hint}</p> : null}
        </div>
    );
}
