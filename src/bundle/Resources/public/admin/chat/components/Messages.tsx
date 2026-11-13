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
            <span className="masilia-chat__avatar" aria-hidden="true">
                <Icon name="ai" size="small" />
            </span>
            <div className="masilia-chat__body">
            <div className="masilia-chat__bubble">
                {waiting ? <p className="masilia-chat__thinking masilia-chat__shimmer" role="status">Thinking…</p> : null}
                <MessagePrimitive.GroupedParts groupBy={groupSteps} indicator="never">
                    {({ part, children }) => {
                        switch (part.type) {
                            case 'group-steps':
                                return <Steps indices={part.indices} running={part.status.type === 'running'}>{children}</Steps>;
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
            </div>
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

const RUNNING: Record<string, string> = {
    explore_site: 'Exploring the site',
    browse_location: 'Looking up content',
};

function Steps({ indices, running, children }: { indices: readonly number[]; running: boolean; children: React.ReactNode }) {
    const parts = useAuiState((state) => state.message.parts) as ReadonlyArray<{ type: string; toolName?: string }>;
    // Open while it works, so the user sees what is going on; once done it folds, unless the user chose.
    const [chosen, setChosen] = useState<boolean | null>(null);
    const open = chosen ?? running;
    const tool = parts[indices[indices.length - 1]]?.toolName;
    const label = running ? `${(tool !== undefined ? RUNNING[tool] : undefined) ?? 'Working'}…` : indices.length === 1 ? '1 step' : `${indices.length} steps`;

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
            <summary className={running ? 'masilia-chat__shimmer' : undefined}>{label}</summary>
            <div className="masilia-chat__steps-list">{children}</div>
        </details>
    );
}
