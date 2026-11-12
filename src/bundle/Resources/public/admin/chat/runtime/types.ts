import type { AssistantRuntime } from '@assistant-ui/react';

/** What the widget needs from the runtime. */
export interface ChatRuntime {
    runtime: AssistantRuntime;
    /** Forgets the conversation, on the server and in the view. */
    clear: () => Promise<void>;
    /** A hook, called inside the runtime's provider: true while a change waits for a decision. */
    useBlocked: () => boolean;
}
