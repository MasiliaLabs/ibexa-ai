import { HttpAgent } from '@ag-ui/client';
import { ExportedMessageRepository } from '@assistant-ui/react';
import type { ThreadHistoryAdapter, ThreadMessageLike } from '@assistant-ui/react';
import { fromAgUiMessages, useAgUiInterrupts, useAgUiRuntime } from '@assistant-ui/react-ag-ui';
import { useMemo, useState } from 'react';

import type { ChatRuntime } from './types';

const RUN_URL = '/admin/api/ai/agent/agui';
const STATE_URL = '/admin/api/ai/agent/agui/state';
const CLEAR_URL = '/admin/api/ai/agent/conversation';

interface AgUiState {
    threadId: string;
    messages: Array<Record<string, unknown>>;
    interrupts: Array<Record<string, unknown>>;
}

/**
 * The server hides a change that awaits approval from the stored messages (it is not a finished tool call
 * yet), and reports it as an interrupt. The view needs the call to show the approval on, so it is added to the
 * last assistant message from the interrupt's own description of it.
 */
export function withPendingCalls(messages: Array<Record<string, unknown>>, interrupts: Array<Record<string, unknown>>): Array<Record<string, unknown>> {
    const calls = interrupts
        .filter((interrupt) => (interrupt.reason === 'tool_call' || interrupt.reason === 'confirmation') && typeof interrupt.id === 'string')
        .map((interrupt) => {
            const metadata = (interrupt.metadata ?? {}) as Record<string, unknown>;
            const inputs = metadata.inputs !== undefined ? metadata.inputs : {};

            return {
                id: String(interrupt.toolCallId ?? interrupt.id),
                type: 'function' as const,
                function: { name: String(metadata.name ?? 'propose_plan'), arguments: JSON.stringify(inputs) },
            };
        });
    if (calls.length === 0) {
        return messages;
    }

    const copy = [...messages];
    const last = copy[copy.length - 1];

    // The change belongs to the turn in progress. When the last stored message is the user's, that turn has no
    // assistant message yet: it gets one. Attaching the call to an older answer would show it in the wrong turn.
    if (last !== undefined && last.role === 'assistant') {
        const known = new Set(((last.toolCalls ?? []) as Array<{ id: string }>).map((call) => call.id));
        copy[copy.length - 1] = { ...last, toolCalls: [...((last.toolCalls ?? []) as unknown[]), ...calls.filter((call) => !known.has(call.id))] };
    } else {
        copy.push({ id: 'pending-change', role: 'assistant', content: '', toolCalls: calls });
    }

    return copy;
}

function createHistory(): ThreadHistoryAdapter {
    return {
        async load() {
            const response = await fetch(STATE_URL, { headers: { Accept: 'application/json' } });
            if (!response.ok) {
                return ExportedMessageRepository.fromArray([]);
            }
            const state = (await response.json()) as AgUiState;
            const merged = withPendingCalls(state.messages, state.interrupts);
            const messages = fromAgUiMessages(merged, { joinAdjacentAssistantMessages: true }) as ThreadMessageLike[];

            if (state.interrupts.length > 0) {
                // The runtime reads the pending interrupts from the metadata of the assistant message they belong to.
                let last = -1;
                for (let i = messages.length - 1; i >= 0; i--) {
                    if (messages[i].role === 'assistant') {
                        last = i;
                        break;
                    }
                }
                if (last !== -1) {
                    const message = messages[last];
                    // A live stream gives the call it names its approval; a restored one is given it here.
                    const gates = new Map(
                        state.interrupts
                            .filter((interrupt) => interrupt.reason === 'tool_call' && typeof interrupt.toolCallId === 'string')
                            .map((interrupt) => [interrupt.toolCallId as string, interrupt]),
                    );
                    const content = typeof message.content === 'string'
                        ? message.content
                        : message.content.map((part) => {
                            const gate = part.type === 'tool-call' && part.toolCallId !== undefined ? gates.get(part.toolCallId) : undefined;

                            return gate === undefined || part.type !== 'tool-call'
                                ? part
                                : { ...part, approval: { id: String(gate.id), ...(typeof gate.message === 'string' ? { prompt: gate.message } : {}) } };
                        });
                    messages[last] = {
                        ...message,
                        content,
                        status: { type: 'requires-action', reason: 'interrupt' },
                        metadata: { ...message.metadata, custom: { ...message.metadata?.custom, agui: { interrupts: state.interrupts } } },
                    };
                }
            }

            return ExportedMessageRepository.fromArray(messages);
        },
        async append() {
            // The server stores the conversation.
        },
    };
}

/** The server takes no new message while an interrupt waits: the box is disabled until it is answered. */
function useBlocked(): boolean {
    return useAgUiInterrupts().length > 0;
}

/**
 * Track B: the AG-UI protocol end to end. Neuron's AG-UI adapter writes the stream, assistant-ui's AG-UI runtime
 * reads it; there is no protocol code of ours on either side.
 */
export function useAguiRuntime(): ChatRuntime {
    const [agent] = useState(() => new HttpAgent({ url: RUN_URL, threadId: 'masilia-agent' }));
    const history = useMemo(() => createHistory(), []);

    const runtime = useAgUiRuntime({
        // In development the package is symlinked, so TypeScript sees @ag-ui/client through two paths and takes the
        // two declarations for different types. It is one module at run time.
        agent: agent as unknown as Parameters<typeof useAgUiRuntime>[0]['agent'],
        adapters: { history },
        // The server stores one assistant record per model step: they are one answer for the user.
        joinAdjacentAssistantMessages: true,
        onError: (error) => {
            // eslint-disable-next-line no-console
            console.error('[masilia-ai] AG-UI error', error);
        },
    });

    return {
        runtime,
        mode: 'agui',
        useBlocked,
        clear: async () => {
            await fetch(CLEAR_URL, { method: 'DELETE' });
            runtime.thread.reset([]);
        },
    };
}
