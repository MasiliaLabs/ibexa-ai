import { HttpAgent } from '@ag-ui/client';
import { ExportedMessageRepository } from '@assistant-ui/react';
import type { ThreadHistoryAdapter, ThreadMessageLike } from '@assistant-ui/react';
import { fromAgUiMessages, useAgUiInterrupts, useAgUiRuntime } from '@assistant-ui/react-ag-ui';
import { useMemo, useRef, useState } from 'react';

import { notify } from '../../shared/notify';
import { runContext } from './pageContext';
import { sharesPage } from './sharePage';
import { isStaleDecision, withInterrupts, withPendingCalls } from './pending';
import type { Wire } from './pending';
import type { ChatRuntime } from './types';

const RUN_URL = '/admin/api/ai/agent/agui';
const STATE_URL = '/admin/api/ai/agent/agui/state';
const CLEAR_URL = '/admin/api/ai/agent/conversation';

interface AgUiState {
    threadId: string;
    messages: Wire[];
    interrupts: Wire[];
}

/** The conversation as the server stores it, with the change that waits for a decision, if any. */
async function loadMessages(): Promise<ThreadMessageLike[]> {
    const response = await fetch(STATE_URL, { headers: { Accept: 'application/json' } });
    if (!response.ok) {
        return [];
    }

    const state = (await response.json()) as AgUiState;
    const messages = fromAgUiMessages(withPendingCalls(state.messages, state.interrupts), { joinAdjacentAssistantMessages: true }) as ThreadMessageLike[];

    return withInterrupts(messages as never[], state.interrupts) as ThreadMessageLike[];
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
    const [agent] = useState(() => new HttpAgent({ url: RUN_URL, threadId: 'masilia-agent' }));
    const history = useMemo<ThreadHistoryAdapter>(
        () => ({
            load: async () => ExportedMessageRepository.fromArray(await loadMessages()),
            async append() {
                // The server stores the conversation.
            },
        }),
        [],
    );

    // The runtime exists once the hook has returned: the error handler reaches it through this holder.
    const holder = useRef<{ reset: (messages: ThreadMessageLike[]) => void } | null>(null);

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
                void loadMessages().then((messages) => holder.current?.reset(messages));
            }
        },
    });
    holder.current = { reset: (messages) => runtime.thread.reset(messages) };

    return {
        runtime,
        useBlocked,
        clear: async () => {
            await fetch(CLEAR_URL, { method: 'DELETE' });
            runtime.thread.reset([]);
        },
    };
}
