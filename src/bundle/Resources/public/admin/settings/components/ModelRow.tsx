import React from 'react';

import { Button } from '../../shared/ids';
import type { Model } from '../types';

interface ModelRowProps {
    model: Model;
    chatActive: boolean;
    imageActive: boolean;
    onEdit: (model: Model) => void;
    onDelete: (model: Model) => void;
}

export function ModelRow({ model, chatActive, imageActive, onEdit, onDelete }: ModelRowProps) {
    return (
        <li className="masilia-settings-model">
            <div className="masilia-settings-model__info">
                <div className="masilia-settings-model__name">
                    {model.name}
                    {chatActive ? <span className="masilia-settings-tag masilia-settings-tag--chat">Chat</span> : null}
                    {imageActive ? <span className="masilia-settings-tag masilia-settings-tag--image">Image</span> : null}
                    {model.supportsImage && !imageActive ? <span className="masilia-settings-tag">Image capable</span> : null}
                    {model.supportsVision ? <span className="masilia-settings-tag">Vision</span> : null}
                </div>
                <div className="masilia-settings-model__details">
                    <code>{model.identifier}</code>
                    <span>Temperature {model.temperature}</span>
                    <span>{model.maxTokens.toLocaleString('en-US')} tokens</span>
                </div>
            </div>
            <div className="masilia-settings-model__actions">
                <Button type="tertiary" size="small" icon="edit" ariaLabel={`Edit ${model.name}`} title="Edit" onClick={() => onEdit(model)} />
                <Button type="tertiary" size="small" icon="trash" ariaLabel={`Delete ${model.name}`} title="Delete" onClick={() => onDelete(model)} />
            </div>
        </li>
    );
}
