import { ActionBarPrimitive, ErrorPrimitive, MessagePrimitive, useAuiState } from '@assistant-ui/react';
import React from 'react';

import { Icon } from '../../shared/ids';
import { Markdown } from './Markdown';
import { ToolCard } from './ToolCard';

export function UserMessage() {
    return (
        <MessagePrimitive.Root className="masilia-chat__message masilia-chat__message--user">
            <div className="masilia-chat__bubble">
                <MessagePrimitive.Parts />
            </div>
        </MessagePrimitive.Root>
    );
}

export function AssistantMessage() {
    const waiting = useAuiState((state) => state.message.status?.type === 'running' && state.message.content.length === 0);

    return (
        <MessagePrimitive.Root className="masilia-chat__message masilia-chat__message--assistant">
            <div className="masilia-chat__bubble">
                {waiting ? <p className="masilia-chat__thinking" role="status">Thinking…</p> : null}
                <MessagePrimitive.Parts components={{ Text: Markdown, tools: { Fallback: ToolCard } }} />
                <MessagePrimitive.Error>
                    <ErrorPrimitive.Root className="masilia-chat__error" role="alert">
                        <ErrorPrimitive.Message />
                    </ErrorPrimitive.Root>
                </MessagePrimitive.Error>
            </div>
            <ActionBarPrimitive.Root className="masilia-chat__message-actions" hideWhenRunning autohide="not-last">
                <ActionBarPrimitive.Copy className="masilia-chat__icon-button" title="Copy" aria-label="Copy the answer">
                    <Icon name="copy" size="small" />
                </ActionBarPrimitive.Copy>
            </ActionBarPrimitive.Root>
        </MessagePrimitive.Root>
    );
}
