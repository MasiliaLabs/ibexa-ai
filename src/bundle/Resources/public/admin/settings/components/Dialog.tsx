import React, { useEffect, useId, useRef } from 'react';
import type { ReactNode } from 'react';

import { Button } from '../../shared/ids';

const FOCUSABLE = 'button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

interface DialogProps {
    title: string;
    /** `panel` slides in from the right (forms); `center` is a small confirmation. */
    variant: 'panel' | 'center';
    onClose: () => void;
    children: ReactNode;
    footer?: ReactNode;
    role?: 'dialog' | 'alertdialog';
}

/**
 * A modal dialog: Escape closes it, Tab stays inside, and the focus goes back to what opened it.
 */
export function Dialog({ title, variant, onClose, children, footer, role = 'dialog' }: DialogProps) {
    const ref = useRef<HTMLDivElement>(null);
    const titleId = useId();
    const onCloseRef = useRef(onClose);
    onCloseRef.current = onClose;

    useEffect(() => {
        const returnTo = document.activeElement instanceof HTMLElement ? document.activeElement : null;
        // The field marked data-autofocus first, then the first field, then the first control.
        const target = ref.current?.querySelector<HTMLElement>('[data-autofocus]')
            ?? ref.current?.querySelector<HTMLElement>('input:not([disabled]), select:not([disabled]), textarea:not([disabled])')
            ?? ref.current?.querySelector<HTMLElement>(FOCUSABLE);
        target?.focus();

        const onKeyDown = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                event.preventDefault();
                onCloseRef.current();

                return;
            }
            if (event.key !== 'Tab' || ref.current === null) {
                return;
            }
            const focusable = Array.from(ref.current.querySelectorAll<HTMLElement>(FOCUSABLE));
            if (focusable.length === 0) {
                return;
            }
            const firstEl = focusable[0];
            const lastEl = focusable[focusable.length - 1];
            const active = document.activeElement;
            if (!ref.current.contains(active)) {
                event.preventDefault();
                firstEl.focus();
            } else if (event.shiftKey && active === firstEl) {
                event.preventDefault();
                lastEl.focus();
            } else if (!event.shiftKey && active === lastEl) {
                event.preventDefault();
                firstEl.focus();
            }
        };

        document.addEventListener('keydown', onKeyDown);

        return () => {
            document.removeEventListener('keydown', onKeyDown);
            returnTo?.focus();
        };
    }, []);

    return (
        <>
            <div className="masilia-settings-overlay" onClick={() => onClose()} />
            <div
                ref={ref}
                className={`masilia-settings-dialog masilia-settings-dialog--${variant}`}
                role={role}
                aria-modal="true"
                aria-labelledby={titleId}
            >
                <header className="masilia-settings-dialog__header">
                    <h2 className="masilia-settings-dialog__title" id={titleId}>{title}</h2>
                    <Button type="tertiary" icon="discard" ariaLabel="Close" title="Close" onClick={onClose} />
                </header>
                <div className="masilia-settings-dialog__body">{children}</div>
                {footer !== undefined ? <footer className="masilia-settings-dialog__footer">{footer}</footer> : null}
            </div>
        </>
    );
}
