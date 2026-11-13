import { ComposerPrimitive, useAui, useAuiState } from '@assistant-ui/react';
import React, { useEffect, useMemo, useRef, useState } from 'react';
import type { KeyboardEvent } from 'react';

import { Icon } from '../../shared/ids';
import { encodeMentions, insertMention, labelFor, mentionTrigger } from '../mentions';
import type { Picked, Trigger } from '../mentions';
import { useMentionSearch } from '../mentionSearch';
import { pageTitle } from '../pageTitle';
import { runContext } from '../runtime/pageContext';
import { setSharesPage, useSharesPage } from '../runtime/sharePage';
import type { ChatRuntime } from '../runtime/types';
import { MentionList } from './MentionList';
import type { MentionOption } from './MentionList';

const LIST_ID = 'masilia-chat-mentions';

/** The page the editor has open, as something that can be mentioned. */
function openPage(): MentionOption | null {
    const page = runContext(window.location.pathname)[0];
    if (page === undefined) {
        return null;
    }
    const ids = JSON.parse(page.value) as { contentId: number; locationId: number };

    return { contentId: ids.contentId, locationId: ids.locationId, name: pageTitle() ?? `Content ${ids.contentId}`, type: 'page', note: 'this page' };
}

/**
 * The message box. Enter sends, Shift+Enter starts a new line, "@" mentions a content. While the assistant
 * answers, send becomes stop. While a change awaits a decision the box is disabled: the server takes no new message
 * until it is decided.
 */
export function Composer({ chat }: { chat: ChatRuntime }) {
    const aui = useAui();
    const running = useAuiState((state) => state.thread.isRunning);
    const text = useAuiState((state) => state.composer.text);
    const disabled = chat.useBlocked();

    const input = useRef<HTMLTextAreaElement>(null);
    const [picked, setPicked] = useState<Picked[]>([]);
    const [trigger, setTrigger] = useState<Trigger | null>(null);
    const [active, setActive] = useState(0);
    const page = useMemo(openPage, []);
    const search = useMentionSearch(trigger?.query ?? null);

    const options = useMemo<MentionOption[]>(() => {
        if (trigger === null) {
            return [];
        }

        return trigger.query === '' ? (page !== null ? [page] : []) : search.items;
    }, [trigger, page, search.items]);
    // The list is open for an "@", and tells what it is doing when it has nothing to offer.
    const open = trigger !== null;

    useEffect(() => setActive(0), [trigger?.query]);

    const refresh = (element: HTMLTextAreaElement) => setTrigger(mentionTrigger(element.value, element.selectionStart ?? element.value.length));

    const choose = (option: MentionOption) => {
        if (trigger === null) {
            return;
        }
        const label = labelFor(option.name, option.contentId, picked);
        const result = insertMention(text, trigger, label);
        setPicked((previous) => [...previous.filter((item) => item.contentId !== option.contentId), { contentId: option.contentId, locationId: option.locationId, name: option.name, label }]);
        aui.composer().setText(result.text);
        setTrigger(null);
        window.requestAnimationFrame(() => {
            input.current?.focus();
            input.current?.setSelectionRange(result.caret, result.caret);
        });
    };

    const submit = () => {
        const current = aui.composer().getState().text;
        if (disabled || running || current.trim() === '') {
            return;
        }
        // The mentions become what the agent and the history understand: the box showed "@label".
        aui.composer().setText(encodeMentions(current, picked));
        aui.composer().send();
        setPicked([]);
        setTrigger(null);
    };

    const onKeyDown = (event: KeyboardEvent<HTMLTextAreaElement>) => {
        if (event.nativeEvent.isComposing) {
            return;
        }
        if (open && options.length > 0) {
            if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                event.preventDefault();
                setActive((index) => (index + (event.key === 'ArrowDown' ? 1 : options.length - 1)) % options.length);

                return;
            }
            if (event.key === 'Enter' || event.key === 'Tab') {
                event.preventDefault();
                choose(options[Math.min(active, options.length - 1)]);

                return;
            }
        }
        if (open && event.key === 'Escape') {
            // Closes the list, not the panel.
            event.preventDefault();
            setTrigger(null);

            return;
        }
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            submit();
        }
    };

    return (
        <ComposerPrimitive.Root className="masilia-chat__composer">
            <div className="masilia-chat__composer-card">
                {open ? <MentionList id={LIST_ID} options={options} active={active} search={search} query={trigger.query} onChoose={choose} onHover={setActive} /> : null}
                <PageChip />
                <label className="visually-hidden" htmlFor="masilia-chat-input">Message the assistant</label>
                <ComposerPrimitive.Input
                    ref={input}
                    id="masilia-chat-input"
                    className="masilia-chat__input"
                    rows={1}
                    maxRows={6}
                    disabled={disabled}
                    // Sending is done here, so that the mentions can be turned into their tokens first.
                    submitMode="none"
                    placeholder={disabled ? 'Approve or refuse the change above first' : 'Ask, or describe a change… (@ to mention a content)'}
                    role="combobox"
                    aria-expanded={open}
                    aria-controls={open ? LIST_ID : undefined}
                    aria-activedescendant={open && options.length > 0 ? `${LIST_ID}-${Math.min(active, options.length - 1)}` : undefined}
                    aria-autocomplete="list"
                    onKeyDown={onKeyDown}
                    onChange={(event) => refresh(event.currentTarget)}
                    onClick={(event) => refresh(event.currentTarget)}
                    onKeyUp={(event) => {
                        // Moving in the list is not moving in the text.
                        if (!['ArrowUp', 'ArrowDown', 'Enter', 'Tab', 'Escape'].includes(event.key)) {
                            refresh(event.currentTarget);
                        }
                    }}
                    onBlur={() => setTrigger(null)}
                />
                <div className="masilia-chat__composer-bar">
                    <span className="masilia-chat__hint">Enter to send · Shift+Enter for a new line · @ to mention</span>
                    {running ? (
                        <ComposerPrimitive.Cancel className="masilia-chat__send" title="Stop" aria-label="Stop">
                            <Icon name="discard" size="small" />
                        </ComposerPrimitive.Cancel>
                    ) : (
                        <button type="button" className="masilia-chat__send masilia-chat__send--primary" title="Send" aria-label="Send" disabled={disabled || text.trim() === ''} onClick={submit}>
                            <Icon name="arrow-up" size="small" />
                        </button>
                    )}
                </div>
            </div>
            <p className="masilia-chat__disclaimer">AI can make mistakes. Review changes before you approve them.</p>
        </ComposerPrimitive.Root>
    );
}

/**
 * Tells the user which page the assistant knows they are on, and lets them stop sharing it.
 */
function PageChip() {
    const shares = useSharesPage();
    const page = useMemo(openPage, []);
    if (page === null) {
        return null;
    }

    return (
        <button
            type="button"
            className={`masilia-chat__chip${shares ? '' : ' masilia-chat__chip--off'}`}
            aria-pressed={shares}
            title={shares ? 'The assistant knows you are on this page. Click to stop sharing it.' : 'Click to let the assistant know which page you are on.'}
            onClick={() => setSharesPage(!shares)}
        >
            <Icon name="file-text" size="small" />
            <span className="masilia-chat__chip-text">{shares ? page.name : `${page.name} (not shared)`}</span>
            <Icon name={shares ? 'discard' : 'add'} size="small" />
        </button>
    );
}
