import { SuggestionPrimitive, ThreadPrimitive, useAuiState } from '@assistant-ui/react';
import React, { useState } from 'react';

import { Button } from '../../shared/ids';
import type { ChatRuntime } from '../runtime/types';
import { Composer } from './Composer';
import { AssistantMessage, UserMessage } from './Messages';

interface ThreadProps {
    chat: ChatRuntime;
    onClose: () => void;
}

export function Thread({ chat, onClose }: ThreadProps) {
    const [confirming, setConfirming] = useState(false);
    const empty = useAuiState((state) => state.thread.isEmpty);

    return (
        <ThreadPrimitive.Root className="masilia-chat__thread">
            <header className="masilia-chat__header">
                <h2 className="masilia-chat__title">Content assistant</h2>
                <div className="masilia-chat__header-actions">
                    {!empty && !confirming ? (
                        <Button type="tertiary" icon="trash" ariaLabel="New conversation" title="New conversation" onClick={() => setConfirming(true)} />
                    ) : null}
                    <Button type="tertiary" icon="discard" ariaLabel="Close" title="Close" onClick={onClose} />
                </div>
            </header>

            {confirming ? (
                <div className="masilia-chat__confirm" role="alertdialog" aria-label="Start a new conversation">
                    <p>Start a new conversation? The current one will be cleared.</p>
                    <div className="masilia-chat__actions">
                        <Button type="secondary" size="small" onClick={() => setConfirming(false)}>Keep it</Button>
                        <Button
                            type="primary"
                            size="small"
                            onClick={() => {
                                setConfirming(false);
                                void chat.clear();
                            }}
                        >
                            Start over
                        </Button>
                    </div>
                </div>
            ) : null}

            <ThreadPrimitive.Viewport className="masilia-chat__viewport">
                <ThreadPrimitive.Empty>
                    <div className="masilia-chat__empty">
                        <p>Ask about the site, or describe a change you want to make.</p>
                        <div className="masilia-chat__suggestions">
                            <ThreadPrimitive.Suggestions>{() => <Suggestion />}</ThreadPrimitive.Suggestions>
                        </div>
                    </div>
                </ThreadPrimitive.Empty>
                <ThreadPrimitive.Messages>
                    {({ message }) => (message.role === 'user' ? <UserMessage /> : <AssistantMessage />)}
                </ThreadPrimitive.Messages>
            </ThreadPrimitive.Viewport>

            <Composer chat={chat} />
        </ThreadPrimitive.Root>
    );
}

function Suggestion() {
    return (
        <SuggestionPrimitive.Trigger send className="masilia-chat__suggestion">
            <span className="masilia-chat__suggestion-title"><SuggestionPrimitive.Title /></span>
            <span className="masilia-chat__suggestion-label"><SuggestionPrimitive.Description /></span>
        </SuggestionPrimitive.Trigger>
    );
}
