import { ActionBarPrimitive, ErrorPrimitive, MessagePrimitive, useAuiState } from '@assistant-ui/react';
import React, { useState } from 'react';

import { Icon } from '../../shared/ids';
import { formatBytes, splitAttachments } from '../attachments';
import type { AttachmentPiece } from '../attachments';
import { attachmentUrl } from '../runtime/uploads';
import { mentionHref, splitMentions } from '../mentions';
import { collectSources } from '../sources';
import { groupSteps } from '../steps';
import type { SourcePart } from '../sources';
import { Markdown } from './Markdown';
import { ToolCard } from './ToolCard';

export function UserMessage() {
    return (
        <MessagePrimitive.Root className="masilia-chat__message masilia-chat__message--user">
            <div className="masilia-chat__bubble">
                <MessagePrimitive.Parts components={{ Text: UserText }} />
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
                <Sources />
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

const RUNNING: Record<string, string> = {
    tool_search: 'Looking for what I can do',
    propose_changes: 'Preparing the changes',
};

/** A capability is a tool named after it, with "__" for the dots ("content.update" is content__update). */
const isCapability = (tool: string | undefined): boolean => tool !== undefined && tool.includes('__');

function Steps({ indices, running, children }: { indices: readonly number[]; running: boolean; children: React.ReactNode }) {
    const parts = useAuiState((state) => state.message.parts) as ReadonlyArray<{ type: string; toolName?: string }>;
    // Open while it works, so the user sees what is going on; once done it folds, unless the user chose.
    const [chosen, setChosen] = useState<boolean | null>(null);
    const open = chosen ?? running;
    const tool = parts[indices[indices.length - 1]]?.toolName;
    const label = running ? `${(tool !== undefined ? RUNNING[tool] : undefined) ?? (isCapability(tool) ? 'Doing it' : 'Working')}…` : indices.length === 1 ? '1 step' : `${indices.length} steps`;

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

interface TurnMessage {
    id: string;
    role: string;
    content: readonly SourcePart[] | string;
}

/**
 * The contents the answer rests on, as links to open them in the admin. While it works, a turn can be several
 * assistant messages (the calls, then the answer): the sources are those of the whole turn, shown under its last
 * message once it is complete.
 */
function Sources() {
    const id = useAuiState((state) => state.message.id);
    const done = useAuiState((state) => state.message.status?.type !== 'running');
    const messages = useAuiState((state) => state.thread.messages) as readonly TurnMessage[];
    const sources = React.useMemo(() => {
        const at = messages.findIndex((message) => message.id === id);
        if (!done || at === -1 || messages[at + 1]?.role === 'assistant') {
            return [];
        }
        const parts: SourcePart[] = [];
        for (let i = at; i >= 0 && messages[i].role === 'assistant'; i--) {
            const content = messages[i].content;
            if (Array.isArray(content)) {
                parts.unshift(...(content as readonly SourcePart[]));
            }
        }

        return collectSources(parts);
    }, [done, id, messages]);
    if (sources.length === 0) {
        return null;
    }

    return (
        <section className="masilia-chat__sources" aria-label="Sources">
            <h4 className="masilia-chat__sources-title">Sources</h4>
            <ul className="masilia-chat__sources-list">
                {sources.map((source) => (
                    <li key={source.contentId}>
                        <a className="masilia-chat__source" href={source.href} target="_blank" rel="noopener noreferrer" title={`Open ${source.name} in a new tab`}>
                            <Icon name="file-text" size="small" />
                            <span className="masilia-chat__source-name">{source.name}</span>
                            {source.type !== null ? <span className="masilia-chat__source-type">{source.type}</span> : null}
                        </a>
                    </li>
                ))}
            </ul>
        </section>
    );
}

/**
 * What the user wrote: a content they mentioned is a link that opens it, and the documents they attached are
 * chips (the text of a document is for the agent, not for the conversation).
 */
function UserText({ text }: { text: string }) {
    const { body, attachments } = splitAttachments(text);

    return (
        <>
            {splitMentions(body).map((piece, index) =>
                piece.type === 'text' ? (
                    <React.Fragment key={index}>{piece.text}</React.Fragment>
                ) : (
                    <a key={index} className="masilia-chat__mention" href={mentionHref(piece)} target="_blank" rel="noopener noreferrer" title={`Open ${piece.name} in a new tab`}>
                        @{piece.name}
                    </a>
                ),
            )}
            {attachments.length > 0 ? (
                <span className="masilia-chat__sent-attachments">
                    {attachments.map((attachment, index) => (
                        <SentAttachment key={index} attachment={attachment} />
                    ))}
                </span>
            ) : null}
        </>
    );
}

/** A file the user attached, as it shows in their message: an image as a thumbnail that opens it, a document as a chip. */
function SentAttachment({ attachment }: { attachment: AttachmentPiece }) {
    // An image is kept for a week: later it is only its name.
    const [gone, setGone] = React.useState(false);
    if (attachment.kind === 'image' && !gone) {
        return (
            <a className="masilia-chat__sent-image" href={attachmentUrl(attachment.id)} target="_blank" rel="noopener noreferrer" title={`${attachment.name} (${attachment.width}×${attachment.height}, ${formatBytes(attachment.bytes)})`}>
                <img src={attachmentUrl(attachment.id)} alt={attachment.name} onError={() => setGone(true)} />
            </a>
        );
    }

    return (
        <span className="masilia-chat__sent-attachment" title={`${attachment.name} (${formatBytes(attachment.bytes)})`}>
            <Icon name="file-text" size="small" />
            <span className="masilia-chat__attachment-name">{attachment.name}</span>
        </span>
    );
}
