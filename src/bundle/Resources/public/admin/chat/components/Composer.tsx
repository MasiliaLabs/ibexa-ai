import { ComposerPrimitive, useAuiState } from '@assistant-ui/react';

import type { ChatRuntime } from '../runtime/types';
import React from 'react';

import { Icon } from '../../shared/ids';

/**
 * The message box. Enter sends, Shift+Enter starts a new line. While the assistant answers, send becomes stop.
 * While a change awaits a decision the box is disabled: the server takes no new message until it is decided.
 */
export function Composer({ chat }: { chat: ChatRuntime }) {
    const running = useAuiState((state) => state.thread.isRunning);
    const disabled = chat.useBlocked();

    return (
        <ComposerPrimitive.Root className="masilia-chat__composer">
            <label className="visually-hidden" htmlFor="masilia-chat-input">Message the assistant</label>
            <ComposerPrimitive.Input
                id="masilia-chat-input"
                className="masilia-chat__input"
                rows={1}
                maxRows={6}
                disabled={disabled}
                placeholder={disabled ? 'Approve or refuse the change above first' : 'Ask, or describe a change…'}
            />
            {running ? (
                <ComposerPrimitive.Cancel className="masilia-chat__send" title="Stop" aria-label="Stop">
                    <Icon name="discard" size="small" />
                </ComposerPrimitive.Cancel>
            ) : (
                <ComposerPrimitive.Send className="masilia-chat__send masilia-chat__send--primary" title="Send" aria-label="Send">
                    <Icon name="arrow-up" size="small" />
                </ComposerPrimitive.Send>
            )}
        </ComposerPrimitive.Root>
    );
}
