import type { ThreadSummary } from './threadsView';

const THREADS_URL = '/admin/api/ai/agent/threads';

/** The user's conversations on this siteaccess, the most recently used first. */
export async function fetchThreads(): Promise<ThreadSummary[]> {
    const response = await fetch(THREADS_URL, { headers: { Accept: 'application/json' } });
    if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
    }

    return ((await response.json()) as { threads: ThreadSummary[] }).threads;
}

export async function deleteThread(id: string): Promise<void> {
    const response = await fetch(`${THREADS_URL}/${encodeURIComponent(id)}`, { method: 'DELETE' });
    if (!response.ok && response.status !== 404) {
        throw new Error(`HTTP ${response.status}`);
    }
}

/** The identifier of a new conversation: made up here, the server creates the conversation on its first message. */
export function newThreadId(): string {
    if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
        return crypto.randomUUID();
    }

    return `t-${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 12)}`;
}
