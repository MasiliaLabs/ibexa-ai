import { SuggestionPrimitive, ThreadPrimitive, useAuiState } from '@assistant-ui/react';
import React, { useState } from 'react';

import { Button, Icon } from '../../shared/ids';
import type { ChatRuntime } from '../runtime/types';
import { Composer } from './Composer';
import { AssistantMessage, UserMessage } from './Messages';

interface ThreadProps {
    chat: ChatRuntime;
    wide: boolean;
    onToggleWide: () => void;
    onClose: () => void;
}

export function Thread({ chat, wide, onToggleWide, onClose }: ThreadProps) {
    const [confirming, setConfirming] = useState(false);
    const empty = useAuiState((state) => state.thread.isEmpty);
    const running = useAuiState((state) => state.thread.isRunning);

    return (
        <ThreadPrimitive.Root className="masilia-chat__thread">
            <header className="masilia-chat__header">
                <span className="masilia-chat__badge" aria-hidden="true">
                    <Icon name="ai" size="small" />
                </span>
                <div className="masilia-chat__heading">
                    <h2 className="masilia-chat__title">Content assistant</h2>
                    <p className="masilia-chat__subtitle" role="status">{running ? 'Working…' : 'Ask, explore, make changes'}</p>
                </div>
                <div className="masilia-chat__header-actions">
                    {!empty && !confirming ? (
                        <Button type="tertiary" icon="message-bubble-edit" ariaLabel="New conversation" title="New conversation" onClick={() => setConfirming(true)} />
                    ) : null}
                    <Button
                        type="tertiary"
                        icon={wide ? 'arrows-inside' : 'arrows-outside'}
                        ariaLabel={wide ? 'Narrow the panel' : 'Widen the panel'}
                        title={wide ? 'Narrow the panel' : 'Widen the panel'}
                        onClick={onToggleWide}
                    />
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
                        <span className="masilia-chat__badge masilia-chat__badge--large" aria-hidden="true">
                            <Icon name="ai" size="medium" />
                        </span>
                        <h3 className="masilia-chat__greeting">How can I help with your content?</h3>
                        <p className="masilia-chat__lead">Ask about the site, or describe a change. Nothing changes until you approve it.</p>
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
            <span className="masilia-chat__suggestion-text">
                <span className="masilia-chat__suggestion-title"><SuggestionPrimitive.Title /></span>
                <span className="masilia-chat__suggestion-label"><SuggestionPrimitive.Description /></span>
            </span>
            <span className="masilia-chat__suggestion-arrow" aria-hidden="true">
                <Icon name="arrow-chevron-right" size="small" />
            </span>
        </SuggestionPrimitive.Trigger>
    );
}
