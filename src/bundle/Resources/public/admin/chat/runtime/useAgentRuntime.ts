import { useAuiState, useExternalStoreRuntime } from '@assistant-ui/react';
import type { ThreadMessageLike } from '@assistant-ui/react';
import { useMemo } from 'react';

import { useAgent } from '../../agent/useAgent';
import { hasPendingApproval, toThreadMessages } from '../messages';
import type { ChatRuntime } from './types';

/** The runtime already reports its composer as disabled while a change awaits. */
function useBlocked(): boolean {
    return useAuiState((state) => state.thread.isDisabled);
}

/**
 * Track A: our own versioned SSE protocol and conversation state (see agent/), shown with assistant-ui.
 * The server is the one the previous widget used; only the view changed.
 */
export function useAgentRuntime(): ChatRuntime {
    const agent = useAgent();
    const messages = useMemo(() => toThreadMessages(agent.state.turns), [agent.state.turns]);

    const runtime = useExternalStoreRuntime<ThreadMessageLike>({
        messages,
        isRunning: agent.state.busy,
        isLoading: agent.loading,
        // The server refuses a new message while a change waits for its decision.
        isDisabled: hasPendingApproval(agent.state.turns),
        convertMessage: (message) => message,
        onNew: async (message) => {
            const text = message.content.map((part) => (part.type === 'text' ? part.text : '')).join('').trim();
            agent.send(text);
        },
        onCancel: async () => {
            agent.stop();
        },
        onRespondToToolApproval: async ({ approvalId, approved, reason }) => {
            agent.decide(approvalId, approved ? { kind: 'approve' } : { kind: 'reject', reason: reason ?? '' });
        },
    });

    return { runtime, clear: agent.clear, mode: 'sse', useBlocked };
}
