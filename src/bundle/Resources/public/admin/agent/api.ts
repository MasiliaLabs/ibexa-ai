/**
 * HTTP calls of the assistant widget. Routes are defined by the PHP controller (AgentChatController).
 */

import type { StoredApproval, StoredMessage } from './state';

const BASE = '/admin/api/ai/agent';

export type RunRequest =
    | { message: string }
    | { decisions: Record<string, 'approve' | ['reject', string]> };

export interface ConversationResponse {
    messages: StoredMessage[];
    pendingApprovals: StoredApproval[];
}

/**
 * Starts a run, or resumes one with decisions. The response is an event stream; read it with readAgentEvents.
 */
export function startRun(request: RunRequest, signal: AbortSignal): Promise<Response> {
    return fetch(`${BASE}/runs`, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'text/event-stream',
        },
        body: JSON.stringify(request),
        signal,
    });
}

export async function loadConversation(signal?: AbortSignal): Promise<ConversationResponse> {
    const response = await fetch(`${BASE}/conversation`, {
        credentials: 'same-origin',
        headers: { Accept: 'application/json' },
        signal,
    });
    if (!response.ok) {
        throw new Error(`Conversation could not be loaded (HTTP ${response.status}).`);
    }

    return (await response.json()) as ConversationResponse;
}

export async function clearConversation(): Promise<void> {
    const response = await fetch(`${BASE}/conversation`, {
        method: 'DELETE',
        credentials: 'same-origin',
        headers: { Accept: 'application/json' },
    });
    if (!response.ok) {
        throw new Error(`Conversation could not be cleared (HTTP ${response.status}).`);
    }
}
