import { HttpAgent } from '@ag-ui/client';
import { ExportedMessageRepository } from '@assistant-ui/react';
import type { ThreadHistoryAdapter, ThreadMessageLike } from '@assistant-ui/react';
import { fromAgUiMessages, useAgUiInterrupts, useAgUiRuntime } from '@assistant-ui/react-ag-ui';
import { useMemo, useRef, useState } from 'react';

import { notify } from '../../shared/notify';
import { runContext } from './pageContext';
import { sharesPage } from './sharePage';
import { isStaleDecision, withInterrupts, withPendingCalls } from './pending';
import { adminHeaders, route } from '../../shared/admin';
import type { Wire } from './pending';
import { deleteThread, newThreadId } from './threads';
import type { ChatRuntime } from './types';


interface AgUiState {
    threadId: string | null;
    messages: Wire[];
    interrupts: Wire[];
}

interface Loaded {
    /** The conversation the messages belong to; null when the user has none yet. */
    threadId: string | null;
    messages: ThreadMessageLike[];
}

/**
 * A conversation as the server stores it, with the change that waits for a decision, if any. Without an id: the
 * one the user was last in.
 */
async function loadConversation(threadId: string | null): Promise<Loaded> {
    const url = route('masilia.ibexa_ai.agent.agui_state', threadId === null ? {} : { threadId });
    const response = await fetch(url, { headers: { Accept: 'application/json' } });
    if (!response.ok) {
        // A conversation that has no message yet is not known to the server: it is empty.
        return { threadId, messages: [] };
    }

    const state = (await response.json()) as AgUiState;
    const messages = fromAgUiMessages(withPendingCalls(state.messages, state.interrupts), { joinAdjacentAssistantMessages: true }) as ThreadMessageLike[];

    return { threadId: state.threadId, messages: withInterrupts(messages as never[], state.interrupts) as ThreadMessageLike[] };
}

/** The server takes no new message while an interrupt waits: the box is disabled until it is answered. */
function useBlocked(): boolean {
    return useAgUiInterrupts().length > 0;
}

/**
 * The assistant's runtime: the AG-UI protocol end to end. Neuron's AG-UI adapter writes the stream on the
 * server (see ReasoningFilteredAGUIAdapter), assistant-ui's AG-UI runtime reads it here. The conversation is
 * the server's: the client sends its last message, or its decision.
 */
export function useAguiRuntime(): ChatRuntime {
    // The conversation in use. A new one is made up here: the server creates it on its first message.
    const current = useRef<string>(newThreadId());
    const [threadId, setThreadId] = useState<string | null>(null);
    const [agent] = useState(() => new HttpAgent({ url: route('masilia.ibexa_ai.agent.agui'), headers: adminHeaders(), threadId: current.current }));

    const use = (id: string): void => {
        current.current = id;
        agent.threadId = id;
        setThreadId(id);
    };

    const history = useMemo<ThreadHistoryAdapter>(
        () => ({
            load: async () => {
                // The widget opens on the conversation the user was last in, or on a new one.
                const loaded = await loadConversation(null);
                use(loaded.threadId ?? current.current);

                return ExportedMessageRepository.fromArray(loaded.messages);
            },
            async append() {
                // The server stores the conversation.
            },
        }),
        // eslint-disable-next-line react-hooks/exhaustive-deps
        [],
    );

    // The runtime exists once the hook has returned: the error handler reaches it through this holder.
    const holder = useRef<{ reset: (messages: ThreadMessageLike[]) => void; cancel: () => void } | null>(null);

    const runtime = useAgUiRuntime({
        // In development the package is symlinked, so TypeScript sees @ag-ui/client through two paths and takes the
        // two declarations for different types. It is one module at run time.
        agent: agent as unknown as Parameters<typeof useAgUiRuntime>[0]['agent'],
        adapters: { history },
        // The server stores one assistant record per model step: they are one answer for the user.
        joinAdjacentAssistantMessages: true,
        // Read when a run starts: the editor may have moved to another page since the widget opened.
        getRunContext: () => (sharesPage() ? runContext(window.location.pathname) : []),
        onError: (error) => {
            // The server is the source of truth: a decision that came too late (decided in another window) leaves
            // the view out of date, so it is read again. Other errors are shown with the answer, as they are.
            if (isStaleDecision(error.message)) {
                notify('info', error.message);
                void loadConversation(current.current).then((loaded) => holder.current?.reset(loaded.messages));
            }
        },
    });
    holder.current = { reset: (messages) => runtime.thread.reset(messages), cancel: () => runtime.thread.cancelRun() };

    return {
        runtime,
        threadId,
        useBlocked,
        startNew: () => {
            holder.current?.cancel();
            use(newThreadId());
            runtime.thread.reset([]);
        },
        select: async (id) => {
            holder.current?.cancel();
            use(id);
            const loaded = await loadConversation(id);
            // The user may have picked another one meanwhile.
            if (current.current === id) {
                runtime.thread.reset(loaded.messages);
            }
        },
        remove: async (id) => {
            await deleteThread(id);
            if (current.current === id) {
                holder.current?.cancel();
                use(newThreadId());
                runtime.thread.reset([]);
            }
        },
    };
}
