import React from 'react';

interface SiteaccessPickerProps {
    legend: string;
    siteaccesses: string[];
    selected: string[];
    currentSiteaccess: string;
    onChange: (selected: string[]) => void;
}

/**
 * Which siteaccesses a provider serves. A siteaccess answers with the provider assigned to it, so each
 * change takes effect as soon as it is saved.
 */
export function SiteaccessPicker({ legend, siteaccesses, selected, currentSiteaccess, onChange }: SiteaccessPickerProps) {
    const toggle = (name: string, checked: boolean) => {
        onChange(checked ? [...selected, name] : selected.filter((value) => value !== name));
    };

    return (
        <fieldset className="masilia-settings-picker">
            <legend className="ids-label masilia-settings-field__label">{legend}</legend>
            <div className="masilia-settings-picker__list">
                {siteaccesses.map((name) => (
                    <label key={name} className="masilia-settings-check masilia-settings-picker__item">
                        <input type="checkbox" checked={selected.includes(name)} onChange={(event) => toggle(name, event.target.checked)} />
                        <span>
                            {name}
                            {name === currentSiteaccess ? <span className="masilia-settings-chip">current</span> : null}
                        </span>
                    </label>
                ))}
            </div>
        </fieldset>
    );
}
