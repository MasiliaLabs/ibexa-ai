import React from 'react';

import { Icon } from '../../shared/ids';
import type { MentionHit, MentionSearch } from '../mentionSearch';

export interface MentionOption extends MentionHit {
    /** Shown instead of the content type, e.g. for the page the editor has open. */
    note?: string;
}

interface MentionListProps {
    id: string;
    options: readonly MentionOption[];
    active: number;
    search: MentionSearch;
    query: string;
    onChoose: (option: MentionOption) => void;
    onHover: (index: number) => void;
}

/**
 * The contents proposed after an "@". It is a listbox the message box points at (aria-activedescendant): the
 * focus stays in the box, the arrow keys move in the list, Enter or Tab chooses.
 */
export function MentionList({ id, options, active, search, query, onChoose, onHover }: MentionListProps) {
    return (
        <div className="masilia-chat__mentions">
            {options.length > 0 ? (
                <ul id={id} className="masilia-chat__mentions-list" role="listbox" aria-label="Contents">
                    {options.map((option, index) => (
                        <li
                            key={option.contentId}
                            id={`${id}-${index}`}
                            role="option"
                            aria-selected={index === active}
                            className="masilia-chat__mention-option"
                            // Choosing must not take the focus from the message box.
                            onMouseDown={(event) => {
                                event.preventDefault();
                                onChoose(option);
                            }}
                            onMouseMove={() => onHover(index)}
                        >
                            <Icon name="file-text" size="small" />
                            <span className="masilia-chat__mention-name">{option.name}</span>
                            <span className="masilia-chat__mention-type">{option.note ?? option.type}</span>
                        </li>
                    ))}
                </ul>
            ) : (
                <p id={id} className="masilia-chat__mentions-note" role="status">
                    {query === '' ? 'Type to search for a content.' : search.status === 'error' ? 'The search failed.' : search.status === 'ready' ? 'No content found.' : 'Searching…'}
                </p>
            )}
        </div>
    );
}
