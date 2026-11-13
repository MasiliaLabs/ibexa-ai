import type { AssistantRuntime } from '@assistant-ui/react';

/** What the widget needs from the runtime. */
export interface ChatRuntime {
    runtime: AssistantRuntime;
    /** The conversation shown; null until the last one has been looked for. */
    threadId: string | null;
    /** Starts an empty conversation. The previous one stays in the history. */
    startNew: () => void;
    /** Shows a conversation of the history. */
    select: (id: string) => Promise<void>;
    /** Forgets a conversation, on the server. When it is the one shown, an empty one takes its place. */
    remove: (id: string) => Promise<void>;
    /** A hook, called inside the runtime's provider: true while a change waits for a decision. */
    useBlocked: () => boolean;
}
