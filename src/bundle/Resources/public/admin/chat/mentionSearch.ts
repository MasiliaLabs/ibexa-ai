import { useEffect, useState } from 'react';

import { route } from '@masilia-admin-kit/admin';

export interface MentionHit {
    contentId: number;
    locationId: number | null;
    name: string;
    type: string;
}

export type MentionSearch = { status: 'idle' | 'loading' | 'error'; items: MentionHit[] } | { status: 'ready'; items: MentionHit[] };

const DEBOUNCE = 180;

/**
 * The contents whose name has what the user typed after the "@". Waits for the typing to pause, and drops an
 * answer that is no longer about what is typed. Nothing is asked for an empty query.
 */
export function useMentionSearch(query: string | null): MentionSearch {
    const [state, setState] = useState<MentionSearch>({ status: 'idle', items: [] });

    useEffect(() => {
        if (query === null || query === '') {
            setState({ status: 'idle', items: [] });

            return undefined;
        }

        const controller = new AbortController();
        setState((previous) => ({ status: 'loading', items: previous.items }));
        const timer = window.setTimeout(() => {
            fetch(route('masilia.ibexa_ai.agent.mentions', { q: query }), { headers: { Accept: 'application/json' }, signal: controller.signal })
                .then((response) => (response.ok ? (response.json() as Promise<{ contents: MentionHit[] }>) : Promise.reject(new Error(`HTTP ${response.status}`))))
                .then((body) => setState({ status: 'ready', items: body.contents }))
                .catch((error: unknown) => {
                    if (!(error instanceof DOMException && error.name === 'AbortError')) {
                        setState({ status: 'error', items: [] });
                    }
                });
        }, DEBOUNCE);

        return () => {
            window.clearTimeout(timer);
            controller.abort();
        };
    }, [query]);

    return state;
}
