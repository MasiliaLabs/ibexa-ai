import React, { useId, useRef } from 'react';
import type { KeyboardEvent, ReactNode } from 'react';

export interface TabDefinition {
    id: string;
    label: string;
    content: ReactNode;
}

interface TabsProps {
    tabs: TabDefinition[];
    active: string;
    onChange: (id: string) => void;
}

/**
 * Tabs with the keyboard behaviour of the ARIA pattern: arrows, Home and End move between them.
 */
export function Tabs({ tabs, active, onChange }: TabsProps) {
    const base = useId();
    const refs = useRef<Record<string, HTMLButtonElement | null>>({});

    const move = (event: KeyboardEvent, index: number) => {
        let target = index;
        if (event.key === 'ArrowRight') {
            target = (index + 1) % tabs.length;
        } else if (event.key === 'ArrowLeft') {
            target = (index - 1 + tabs.length) % tabs.length;
        } else if (event.key === 'Home') {
            target = 0;
        } else if (event.key === 'End') {
            target = tabs.length - 1;
        } else {
            return;
        }
        event.preventDefault();
        onChange(tabs[target].id);
        refs.current[tabs[target].id]?.focus();
    };

    return (
        <div>
            <div className="masilia-settings-tabs" role="tablist" aria-label="AI settings">
                {tabs.map((tab, index) => (
                    <button
                        key={tab.id}
                        ref={(node) => { refs.current[tab.id] = node; }}
                        type="button"
                        role="tab"
                        id={`${base}-tab-${tab.id}`}
                        aria-selected={tab.id === active}
                        aria-controls={`${base}-panel-${tab.id}`}
                        tabIndex={tab.id === active ? 0 : -1}
                        className={`masilia-settings-tabs__tab${tab.id === active ? ' is-active' : ''}`}
                        onClick={() => onChange(tab.id)}
                        onKeyDown={(event) => move(event, index)}
                    >
                        {tab.label}
                    </button>
                ))}
            </div>
            {tabs.map((tab) => (
                tab.id === active ? (
                    <div key={tab.id} role="tabpanel" id={`${base}-panel-${tab.id}`} aria-labelledby={`${base}-tab-${tab.id}`} className="masilia-settings-panel">
                        {tab.content}
                    </div>
                ) : null
            ))}
        </div>
    );
}
