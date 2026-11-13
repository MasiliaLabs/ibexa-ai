import { ComposerPrimitive, useAuiState } from '@assistant-ui/react';
import React from 'react';

import { Icon } from '../../shared/ids';
import { pageTitle } from '../pageTitle';
import { runContext } from '../runtime/pageContext';
import { setSharesPage, useSharesPage } from '../runtime/sharePage';
import type { ChatRuntime } from '../runtime/types';

/**
 * The message box. Enter sends, Shift+Enter starts a new line. While the assistant answers, send becomes stop.
 * While a change awaits a decision the box is disabled: the server takes no new message until it is decided.
 */
export function Composer({ chat }: { chat: ChatRuntime }) {
    const running = useAuiState((state) => state.thread.isRunning);
    const disabled = chat.useBlocked();

    return (
        <ComposerPrimitive.Root className="masilia-chat__composer">
            <div className="masilia-chat__composer-card">
                <PageChip />
                <label className="visually-hidden" htmlFor="masilia-chat-input">Message the assistant</label>
                <ComposerPrimitive.Input
                    id="masilia-chat-input"
                    className="masilia-chat__input"
                    rows={1}
                    maxRows={6}
                    disabled={disabled}
                    placeholder={disabled ? 'Approve or refuse the change above first' : 'Ask, or describe a change…'}
                />
                <div className="masilia-chat__composer-bar">
                    <span className="masilia-chat__hint">Enter to send · Shift+Enter for a new line</span>
                    {running ? (
                        <ComposerPrimitive.Cancel className="masilia-chat__send" title="Stop" aria-label="Stop">
                            <Icon name="discard" size="small" />
                        </ComposerPrimitive.Cancel>
                    ) : (
                        <ComposerPrimitive.Send className="masilia-chat__send masilia-chat__send--primary" title="Send" aria-label="Send">
                            <Icon name="arrow-up" size="small" />
                        </ComposerPrimitive.Send>
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
    const page = runContext(window.location.pathname)[0];
    if (page === undefined) {
        return null;
    }

    const title = pageTitle() ?? `Content ${(JSON.parse(page.value) as { contentId: number }).contentId}`;

    return (
        <button
            type="button"
            className={`masilia-chat__chip${shares ? '' : ' masilia-chat__chip--off'}`}
            aria-pressed={shares}
            title={shares ? 'The assistant knows you are on this page. Click to stop sharing it.' : 'Click to let the assistant know which page you are on.'}
            onClick={() => setSharesPage(!shares)}
        >
            <Icon name="file-text" size="small" />
            <span className="masilia-chat__chip-text">{shares ? title : `${title} (not shared)`}</span>
            <Icon name={shares ? 'discard' : 'add'} size="small" />
        </button>
    );
}
