/**
 * Our conversation state as assistant-ui messages. Pure: no React, no network.
 *
 * A turn becomes a user message and an assistant message. The assistant message holds tool calls and text; a
 * change awaiting the user's decision is a tool call carrying an `approval`, which assistant-ui renders and
 * answers through `respondToApproval`.
 */

import type { ThreadMessageLike } from '@assistant-ui/react';

import type { Approval, ToolStep, Turn, TurnStatus } from '../agent/state';

type Content = Exclude<ThreadMessageLike['content'], string>;
type Part = Content[number];
type ToolCallPart = Extract<Part, { type: 'tool-call' }>;
type Status = NonNullable<ThreadMessageLike['status']>;

function statusOf(status: TurnStatus, error: string | null): Status {
    switch (status) {
        case 'streaming':
            return { type: 'running' };
        case 'awaiting':
            return { type: 'requires-action', reason: 'interrupt' };
        case 'failed':
            return { type: 'incomplete', reason: 'error', error: error ?? 'The assistant failed.' };
        default:
            return { type: 'complete', reason: 'stop' };
    }
}

function approvalOf(approval: Approval): NonNullable<ToolCallPart['approval']> {
    const decision = approval.decision;

    return {
        id: approval.id,
        prompt: approval.reason,
        ...(decision === null ? {} : { approved: decision.kind === 'approve' }),
        ...(decision !== null && decision.kind === 'reject' && decision.reason !== '' ? { reason: decision.reason } : {}),
    };
}

function toolPart(step: ToolStep, approval: Approval | undefined): ToolCallPart {
    const refused = approval?.decision?.kind === 'reject';
    const settled = step.status !== 'running' || refused;

    return {
        type: 'tool-call',
        toolCallId: step.id,
        toolName: step.tool,
        args: step.inputs as ToolCallPart['args'],
        ...(settled ? { result: step.message !== '' ? step.message : refused ? 'Refused.' : 'Done.' } : {}),
        ...(step.status === 'failed' ? { isError: true } : {}),
        ...(approval !== undefined ? { approval: approvalOf(approval) } : {}),
    };
}

/** A change that has no tool step of its own (restored from the history after a reload). */
function approvalOnlyPart(approval: Approval): ToolCallPart {
    return {
        type: 'tool-call',
        toolCallId: approval.id,
        toolName: approval.tool,
        args: approval.inputs as ToolCallPart['args'],
        ...(approval.decision?.kind === 'reject' ? { result: 'Refused.' } : {}),
        approval: approvalOf(approval),
    };
}

function assistantContent(turn: Turn): Part[] {
    const approvals = new Map(turn.approvals.map((approval) => [approval.id, approval]));
    const stepIds = new Set(turn.tools.map((step) => step.id));

    const plainTools = turn.tools.filter((step) => !approvals.has(step.id)).map((step) => toolPart(step, undefined));
    const gated = [
        ...turn.tools.filter((step) => approvals.has(step.id)).map((step) => toolPart(step, approvals.get(step.id))),
        ...turn.approvals.filter((approval) => !stepIds.has(approval.id)).map(approvalOnlyPart),
    ];

    const parts: Part[] = [...plainTools];
    const text = (value: string): void => {
        if (value.trim() !== '') {
            parts.push({ type: 'text', text: value });
        }
    };

    if (turn.resumeAt !== null) {
        // The assistant wrote more after the user's decision: it belongs below the change.
        text(turn.text.slice(0, turn.resumeAt));
        parts.push(...gated);
        text(turn.text.slice(turn.resumeAt));
    } else {
        text(turn.text);
        parts.push(...gated);
    }

    return parts;
}

export function toThreadMessages(turns: readonly Turn[]): ThreadMessageLike[] {
    const messages: ThreadMessageLike[] = [];

    for (const turn of turns) {
        if (turn.user !== null) {
            messages.push({ id: `${turn.id}-user`, role: 'user', content: [{ type: 'text', text: turn.user }] });
        }
        messages.push({
            id: `${turn.id}-assistant`,
            role: 'assistant',
            content: assistantContent(turn),
            status: statusOf(turn.status, turn.error),
        });
    }

    return messages;
}

/** True while the last turn holds a change that still needs a decision: the composer waits. */
export function hasPendingApproval(turns: readonly Turn[]): boolean {
    const last = turns[turns.length - 1];

    return last !== undefined && last.approvals.some((approval) => approval.decision === null);
}
