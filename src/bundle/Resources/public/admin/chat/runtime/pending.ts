/**
 * Restoring a conversation that waits for a decision. Pure: no React, no network.
 *
 * The server stores the conversation, and reports a change awaiting approval as an interrupt, not as a message:
 * a tool call that was not dispatched yet is not part of the stored history. The view needs that call to show
 * the approval on, so it is rebuilt from the interrupt's own description of it.
 */

export type Wire = Record<string, unknown>;

interface Interrupt extends Wire {
    id?: unknown;
    reason?: unknown;
    toolCallId?: unknown;
    message?: unknown;
    metadata?: unknown;
}

function isGate(interrupt: Interrupt): boolean {
    return (interrupt.reason === 'tool_call' || interrupt.reason === 'confirmation') && typeof interrupt.id === 'string';
}

/**
 * The stored messages with the calls the interrupts name. The calls belong to the turn in progress: when the
 * last stored message is the user's, that turn has no assistant message yet, so it gets one. Attaching a call to
 * an older answer would show it in the wrong turn.
 */
export function withPendingCalls(messages: Wire[], interrupts: Wire[]): Wire[] {
    const calls = (interrupts as Interrupt[]).filter(isGate).map((interrupt) => {
        const metadata = (typeof interrupt.metadata === 'object' && interrupt.metadata !== null ? interrupt.metadata : {}) as Wire;

        return {
            id: String(interrupt.toolCallId ?? interrupt.id),
            type: 'function' as const,
            function: { name: String(metadata.name ?? 'propose_plan'), arguments: JSON.stringify(metadata.inputs ?? {}) },
        };
    });
    if (calls.length === 0) {
        return messages;
    }

    const copy = [...messages];
    const last = copy[copy.length - 1];
    if (last !== undefined && last.role === 'assistant') {
        const known = new Set(((last.toolCalls ?? []) as Array<{ id: string }>).map((call) => call.id));
        copy[copy.length - 1] = { ...last, toolCalls: [...((last.toolCalls ?? []) as unknown[]), ...calls.filter((call) => !known.has(call.id))] };
    } else {
        copy.push({ id: 'pending-change', role: 'assistant', content: '', toolCalls: calls });
    }

    return copy;
}

interface ToolPart {
    type: string;
    toolCallId?: string;
    approval?: unknown;
    [key: string]: unknown;
}

interface MessageLike {
    role: string;
    content: string | readonly ToolPart[];
    status?: unknown;
    metadata?: { custom?: Record<string, unknown>; [key: string]: unknown };
    [key: string]: unknown;
}

/**
 * Marks the last assistant message as waiting and gives each call an interrupt names its approval, as a live
 * stream does. The runtime reads the pending interrupts from the metadata of the message they belong to.
 */
export function withInterrupts<T extends MessageLike>(messages: T[], interrupts: Wire[]): T[] {
    if (interrupts.length === 0) {
        return messages;
    }

    let last = -1;
    for (let i = messages.length - 1; i >= 0; i--) {
        if (messages[i].role === 'assistant') {
            last = i;
            break;
        }
    }
    if (last === -1) {
        return messages;
    }

    const gates = new Map(
        (interrupts as Interrupt[])
            .filter((interrupt) => interrupt.reason === 'tool_call' && typeof interrupt.toolCallId === 'string')
            .map((interrupt) => [interrupt.toolCallId as string, interrupt]),
    );
    const message = messages[last];
    const content = typeof message.content === 'string'
        ? message.content
        : message.content.map((part) => {
            const gate = part.type === 'tool-call' && part.toolCallId !== undefined ? gates.get(part.toolCallId) : undefined;

            return gate === undefined
                ? part
                : { ...part, approval: { id: String(gate.id), ...(typeof gate.message === 'string' ? { prompt: gate.message } : {}) } };
        });

    const copy = [...messages];
    copy[last] = {
        ...message,
        content,
        status: { type: 'requires-action', reason: 'interrupt' },
        metadata: { ...message.metadata, custom: { ...message.metadata?.custom, agui: { interrupts } } },
    };

    return copy;
}

/**
 * Whether an error says a decision came too late (the change was decided in another window). The conversation
 * shown is then out of date and is read again from the server.
 */
export function isStaleDecision(message: string): boolean {
    return /already decided/i.test(message);
}
