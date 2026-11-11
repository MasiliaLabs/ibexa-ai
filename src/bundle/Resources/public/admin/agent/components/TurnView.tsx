import React from 'react';
import type { Decision, Turn } from '../state';
import { ApprovalCard } from './ApprovalCard';
import { Markdown } from './Markdown';
import { ToolTimeline } from './ToolTimeline';

interface TurnViewProps {
    turn: Turn;
    active: boolean;
    onDecide: (approvalId: string, decision: Decision) => void;
}

/**
 * One exchange, in reading order: the user's message, what the assistant did, what it said,
 * the changes to review, then what it said after the user's decision.
 * The user's text is shown as written; the assistant's is read as Markdown (see Markdown.tsx), never as HTML.
 */
export function TurnView({ turn, active, onDecide }: TurnViewProps) {
    const streaming = turn.status === 'streaming' && active;
    const cut = turn.resumeAt ?? turn.text.length;
    const before = turn.text.slice(0, cut).trim();
    const after = turn.text.slice(cut).trim();
    const waitingForText =
        streaming && (turn.resumeAt !== null ? after === '' : before === '' && turn.tools.length === 0);

    return (
        <article className="masilia-agent__turn">
            {turn.user !== null ? (
                <p className="masilia-agent__message masilia-agent__message--user">
                    <span className="visually-hidden">You: </span>
                    {turn.user}
                </p>
            ) : null}

            {turn.tools.length > 0 ? <ToolTimeline steps={turn.tools} /> : null}

            {before !== '' ? <AssistantMessage text={before} /> : null}

            {turn.approvals.map((approval) => (
                <ApprovalCard key={approval.id} approval={approval} onDecide={onDecide} />
            ))}

            {after !== '' ? <AssistantMessage text={after} /> : null}

            {waitingForText ? (
                <p className="masilia-agent__typing" aria-label="The assistant is working">
                    <span />
                    <span />
                    <span />
                </p>
            ) : null}

            {turn.status === 'failed' ? (
                <p className="masilia-agent__error" role="alert">
                    {turn.error ?? 'The assistant could not answer.'}
                </p>
            ) : null}
        </article>
    );
}

function AssistantMessage({ text }: { text: string }) {
    return (
        <div className="masilia-agent__message masilia-agent__message--assistant">
            <span className="visually-hidden">Assistant: </span>
            <Markdown text={text} />
        </div>
    );
}
