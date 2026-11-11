/**
 * Conversation state of the assistant widget. Pure: no React, no network, so it can be tested directly.
 *
 * A turn is one user message and everything the assistant did in answer to it: text, tool calls and
 * the changes waiting for a decision. A decision request resumes the same turn.
 */

import type { AgentEvent, Inputs } from './protocol';

export type ToolStatus = 'running' | 'ok' | 'failed';

export interface ToolStep {
    id: string;
    tool: string;
    inputs: Inputs;
    status: ToolStatus;
    message: string;
}

export type Decision = { kind: 'approve' } | { kind: 'reject'; reason: string };

export interface Approval {
    id: string;
    tool: string;
    reason: string;
    inputs: Inputs;
    decision: Decision | null;
}

export type TurnStatus = 'streaming' | 'awaiting' | 'done' | 'failed';

export interface Turn {
    id: string;
    user: string | null;
    text: string;
    tools: ToolStep[];
    approvals: Approval[];
    status: TurnStatus;
    error: string | null;
}

export interface AgentState {
    turns: Turn[];
    busy: boolean;
}

export interface StoredMessage {
    role: 'user' | 'assistant';
    text: string;
}

export interface StoredApproval {
    id: string;
    tool: string;
    reason: string;
    inputs: Inputs;
}

export type Action =
    | { type: 'turn.started'; turnId: string; text: string }
    | { type: 'event'; event: AgentEvent }
    | { type: 'decision.recorded'; approvalId: string; decision: Decision }
    | { type: 'decisions.sent' }
    | { type: 'stream.ended' }
    | { type: 'stream.stopped' }
    | { type: 'stream.failed'; message: string }
    | { type: 'restored'; messages: StoredMessage[]; pending: StoredApproval[] }
    | { type: 'cleared' };

export const initialState: AgentState = { turns: [], busy: false };

export function reducer(state: AgentState, action: Action): AgentState {
    switch (action.type) {
        case 'turn.started':
            return {
                turns: [...state.turns, newTurn(action.turnId, action.text, 'streaming')],
                busy: true,
            };

        case 'event':
            return applyEvent(state, action.event);

        case 'decision.recorded':
            return updateLastTurn(state, (turn) => ({
                ...turn,
                approvals: turn.approvals.map((approval) =>
                    approval.id === action.approvalId ? { ...approval, decision: action.decision } : approval,
                ),
            }));

        case 'decisions.sent':
            return {
                turns: updateLastTurnStatus(state.turns, 'streaming'),
                busy: true,
            };

        case 'stream.ended':
            return { turns: settleLastTurn(state.turns, 'done'), busy: false };

        case 'stream.stopped':
            // The server keeps the run going; the widget only stops showing it.
            return { turns: settleLastTurn(state.turns, 'done'), busy: false };

        case 'stream.failed':
            return { turns: settleLastTurn(state.turns, 'failed', action.message), busy: false };

        case 'restored':
            return { turns: restoreTurns(action.messages, action.pending), busy: false };

        case 'cleared':
            return initialState;

        default:
            return state;
    }
}

/** Approvals of the last turn that still need a decision. */
export function pendingApprovals(state: AgentState): Approval[] {
    const last = state.turns[state.turns.length - 1];

    return last === undefined ? [] : last.approvals.filter((approval) => approval.decision === null);
}

/** Decisions to submit: only when every pending change has one. Null while some are still open. */
export function decisionsPayload(state: AgentState): Record<string, 'approve' | ['reject', string]> | null {
    const last = state.turns[state.turns.length - 1];
    if (last === undefined || last.approvals.length === 0) {
        return null;
    }

    const payload: Record<string, 'approve' | ['reject', string]> = {};
    for (const approval of last.approvals) {
        if (approval.decision === null) {
            return null;
        }
        payload[approval.id] = approval.decision.kind === 'approve' ? 'approve' : ['reject', approval.decision.reason];
    }

    return payload;
}

function applyEvent(state: AgentState, event: AgentEvent): AgentState {
    switch (event.type) {
        case 'text.delta':
            return updateLastTurn(state, (turn) => ({ ...turn, text: turn.text + event.text }));

        case 'tool.started':
            // Text written before and after a tool call is one block: separate the parts so they do not run together.
            return updateLastTurn(state, (turn) => ({
                ...turn,
                text: turn.text !== '' && !turn.text.endsWith('\n\n') ? `${turn.text}\n\n` : turn.text,
                tools: [...turn.tools, { id: event.id, tool: event.tool, inputs: event.inputs, status: 'running', message: '' }],
            }));

        case 'tool.finished':
            return updateLastTurn(state, (turn) => ({
                ...turn,
                tools: turn.tools.map((step) =>
                    step.id === event.id
                        ? { ...step, status: event.ok ? 'ok' : 'failed', message: event.message }
                        : step,
                ),
            }));

        case 'approval.requested':
            return updateLastTurn(state, (turn) => ({
                ...turn,
                approvals: [
                    ...turn.approvals.filter((approval) => approval.id !== event.id),
                    { id: event.id, tool: event.tool, reason: event.reason, inputs: event.inputs, decision: null },
                ],
            }));

        case 'run.completed':
            return {
                turns: settleLastTurn(state.turns, event.interrupted ? 'awaiting' : 'done'),
                busy: false,
            };

        case 'run.error':
            return { turns: settleLastTurn(state.turns, 'failed', event.message), busy: false };

        default:
            return state;
    }
}

function newTurn(id: string, user: string | null, status: TurnStatus): Turn {
    return { id, user, text: '', tools: [], approvals: [], status, error: null };
}

function updateLastTurn(state: AgentState, update: (turn: Turn) => Turn): AgentState {
    if (state.turns.length === 0) {
        return state;
    }

    const turns = [...state.turns];
    turns[turns.length - 1] = update(turns[turns.length - 1]);

    return { ...state, turns };
}

function updateLastTurnStatus(turns: Turn[], status: TurnStatus): Turn[] {
    if (turns.length === 0) {
        return turns;
    }

    const copy = [...turns];
    copy[copy.length - 1] = { ...copy[copy.length - 1], status, error: null };

    return copy;
}

function settleLastTurn(turns: Turn[], status: TurnStatus, error: string | null = null): Turn[] {
    if (turns.length === 0) {
        return turns;
    }

    const copy = [...turns];
    const last = copy[copy.length - 1];
    copy[copy.length - 1] = { ...last, status, error };

    return copy;
}

/**
 * Rebuilds turns from the stored history. Consecutive assistant messages belong to the turn of the
 * user message before them. Changes still awaiting a decision are attached to the last turn.
 */
export function restoreTurns(messages: StoredMessage[], pending: StoredApproval[]): Turn[] {
    const turns: Turn[] = [];

    for (const message of messages) {
        if (message.role === 'user') {
            turns.push(newTurn(`restored-${turns.length}`, message.text, 'done'));
        } else {
            if (turns.length === 0) {
                turns.push(newTurn(`restored-${turns.length}`, null, 'done'));
            }
            const last = turns[turns.length - 1];
            turns[turns.length - 1] = { ...last, text: last.text === '' ? message.text : `${last.text}\n\n${message.text}` };
        }
    }

    if (pending.length > 0 && turns.length > 0) {
        const last = turns[turns.length - 1];
        turns[turns.length - 1] = {
            ...last,
            status: 'awaiting',
            approvals: pending.map((approval) => ({ ...approval, decision: null })),
        };
    }

    return turns;
}
