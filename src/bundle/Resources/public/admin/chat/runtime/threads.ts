import type { ThreadSummary } from './threadsView';

import { csrfHeaders, route } from '@masilia-admin-kit/admin';

const threadsUrl = (): string => route('masilia.ibexa_ai.agent.threads');
const threadUrl = (id: string): string => route('masilia.ibexa_ai.agent.thread_delete', { threadId: id });

/** The user's conversations on this siteaccess, the most recently used first. */
export async function fetchThreads(): Promise<ThreadSummary[]> {
    const response = await fetch(threadsUrl(), { headers: { Accept: 'application/json' } });
    if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
    }

    return ((await response.json()) as { threads: ThreadSummary[] }).threads;
}

export async function deleteThread(id: string): Promise<void> {
    const response = await fetch(threadUrl(id), { method: 'DELETE', headers: csrfHeaders() });
    if (!response.ok && response.status !== 404) {
        throw new Error(`HTTP ${response.status}`);
    }
}

/** Gives a conversation a new title; returns the title the server kept (one line, cut at its limit). */
export async function renameThread(id: string, title: string): Promise<string> {
    const response = await fetch(threadUrl(id), {
        method: 'PATCH',
        headers: csrfHeaders({ 'Content-Type': 'application/json', Accept: 'application/json' }),
        body: JSON.stringify({ title }),
    });
    if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
    }

    return ((await response.json()) as { title: string }).title;
}

/** The identifier of a new conversation: made up here, the server creates the conversation on its first message. */
export function newThreadId(): string {
    if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
        return crypto.randomUUID();
    }

    return `t-${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 12)}`;
}
