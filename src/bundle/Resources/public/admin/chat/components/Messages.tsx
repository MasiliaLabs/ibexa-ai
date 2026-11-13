import { ActionBarPrimitive, ErrorPrimitive, MessagePrimitive, useAuiState } from '@assistant-ui/react';
import React, { useState } from 'react';

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
                <MessagePrimitive.GroupedParts groupBy={groupSteps} indicator="never">
                    {({ part, children }) => {
                        switch (part.type) {
                            case 'group-steps':
                                return <Steps count={part.indices.length} running={part.status.type === 'running'}>{children}</Steps>;
                            case 'text':
                                return <Markdown />;
                            case 'tool-call':
                                return part.toolUI ?? <ToolCard {...part} />;
                            default:
                                return null;
                        }
                    }}
                </MessagePrimitive.GroupedParts>
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

/**
 * What the assistant does to look around (explore, browse) is folded into one line; a proposed change is not: it
 * asks for a decision and stays in the open.
 */
function groupSteps(part: { type: string; toolName?: string }): readonly `group-${string}`[] {
    return part.type === 'tool-call' && part.toolName !== 'propose_plan' ? ['group-steps'] : [];
}

function Steps({ count, running, children }: { count: number; running: boolean; children: React.ReactNode }) {
    // Open while it works, so the user sees what is going on; once done it folds, unless the user chose.
    const [chosen, setChosen] = useState<boolean | null>(null);
    const open = chosen ?? running;

    return (
        <details
            className="masilia-chat__steps"
            open={open}
            onToggle={(event) => {
                const next = (event.currentTarget as HTMLDetailsElement).open;
                if (next !== open) {
                    setChosen(next);
                }
            }}
        >
            <summary>{running ? 'Working…' : count === 1 ? '1 step' : `${count} steps`}</summary>
            <div className="masilia-chat__steps-list">{children}</div>
        </details>
    );
}
