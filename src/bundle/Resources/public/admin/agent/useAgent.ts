import { useCallback, useEffect, useReducer, useRef, useState } from 'react';

import { clearConversation, loadConversation, startRun } from './api';
import type { RunRequest } from './api';
import { readAgentEvents } from './protocol';
import { decisionsPayload, initialState, pendingApprovals, reducer } from './state';
import type { AgentState, Decision } from './state';

export interface AgentController {
    state: AgentState;
    loading: boolean;
    send: (text: string) => void;
    decide: (approvalId: string, decision: Decision) => void;
    stop: () => void;
    clear: () => Promise<void>;
}

/**
 * Owns the widget's conversation: restores it on mount, streams runs and decision submissions,
 * and submits decisions once every pending change has one.
 */
export function useAgent(): AgentController {
    const [state, dispatch] = useReducer(reducer, initialState);
    const [loading, setLoading] = useState(true);
    const stateRef = useRef(state);
    stateRef.current = state;
    const controllerRef = useRef<AbortController | null>(null);
    const turnCounter = useRef(0);

    useEffect(() => {
        const controller = new AbortController();
        setLoading(true);
        loadConversation(controller.signal)
            .then((conversation) =>
                dispatch({
                    type: 'restored',
                    messages: conversation.messages,
                    pending: conversation.pendingApprovals,
                }),
            )
            .catch(() => {
                // Nothing to restore (or the request was cancelled): the widget starts empty.
            })
            .finally(() => {
                // A cancelled first request (React StrictMode mounts twice) must not end the loading of the second.
                if (!controller.signal.aborted) {
                    setLoading(false);
                }
            });

        return () => controller.abort();
    }, [setLoading]);

    const reloadConversation = useCallback(async () => {
        try {
            const conversation = await loadConversation();
            dispatch({ type: 'restored', messages: conversation.messages, pending: conversation.pendingApprovals });
        } catch {
            // The error message of the run is already shown; the conversation stays as it is.
        }
    }, []);

    const consume = useCallback(async (request: RunRequest) => {
        controllerRef.current?.abort();
        const controller = new AbortController();
        controllerRef.current = controller;

        try {
            const response = await startRun(request, controller.signal);
            if (response.body === null) {
                dispatch({ type: 'stream.failed', message: 'The assistant returned no answer.' });

                return;
            }

            let sawEnd = false;
            for await (const event of readAgentEvents(response.body)) {
                dispatch({ type: 'event', event });
                if (event.type === 'run.completed' || event.type === 'run.error') {
                    sawEnd = true;
                }
                if (event.type === 'run.error' && event.code === 'ALREADY_DECIDED') {
                    // Another window decided this change: the server knows the real state, show it.
                    await reloadConversation();
                }
            }

            if (!sawEnd) {
                dispatch({ type: 'stream.ended' });
            }
        } catch (error) {
            if (controller.signal.aborted) {
                dispatch({ type: 'stream.stopped' });
            } else {
                dispatch({ type: 'stream.failed', message: error instanceof Error ? error.message : 'Network error.' });
            }
        } finally {
            if (controllerRef.current === controller) {
                controllerRef.current = null;
            }
        }
    }, [reloadConversation]);

    const send = useCallback(
        (text: string) => {
            const trimmed = text.trim();
            if (trimmed === '' || stateRef.current.busy) {
                return;
            }
            turnCounter.current += 1;
            dispatch({ type: 'turn.started', turnId: `turn-${turnCounter.current}`, text: trimmed });
            void consume({ message: trimmed });
        },
        [consume],
    );

    const decide = useCallback(
        (approvalId: string, decision: Decision) => {
            if (stateRef.current.busy) {
                return;
            }
            dispatch({ type: 'decision.recorded', approvalId, decision });

            // Compute the next state here, because the reducer runs later.
            const next = reducer(stateRef.current, { type: 'decision.recorded', approvalId, decision });
            if (pendingApprovals(next).length > 0) {
                return;
            }
            const payload = decisionsPayload(next);
            if (payload === null) {
                return;
            }
            dispatch({ type: 'decisions.sent' });
            void consume({ decisions: payload });
        },
        [consume],
    );

    const stop = useCallback(() => {
        controllerRef.current?.abort();
    }, []);

    const clear = useCallback(async () => {
        controllerRef.current?.abort();
        await clearConversation();
        dispatch({ type: 'cleared' });
    }, []);

    return { state, loading, send, decide, stop, clear };
}
