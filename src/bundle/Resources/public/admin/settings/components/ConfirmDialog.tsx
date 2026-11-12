import React from 'react';

import { Button } from '../../shared/ids';
import { Dialog } from './Dialog';

interface ConfirmDialogProps {
    title: string;
    description: string;
    confirmLabel: string;
    onConfirm: () => void;
    onCancel: () => void;
}

export function ConfirmDialog({ title, description, confirmLabel, onConfirm, onCancel }: ConfirmDialogProps) {
    return (
        <Dialog
            title={title}
            variant="center"
            role="alertdialog"
            onClose={onCancel}
            footer={(
                <>
                    <Button type="tertiary" onClick={onCancel}>Cancel</Button>
                    <Button type="primary" onClick={onConfirm}>{confirmLabel}</Button>
                </>
            )}
        >
            <p className="masilia-settings-dialog__text">{description}</p>
        </Dialog>
    );
}
