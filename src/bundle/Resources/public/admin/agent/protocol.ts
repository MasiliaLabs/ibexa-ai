/**
 * Agent stream protocol, version 1.
 *
 * The server sends named Server-Sent Events: `event: <name>` then `data: <JSON>`, frames separated by a blank line.
 * Every payload carries `v: 1`. Unknown events and other versions are ignored, so the server can add events
 * without breaking an open widget.
 */

export const PROTOCOL_VERSION = 1;

export type Inputs = Record<string, unknown>;

export type AgentEvent =
    | { type: 'text.delta'; text: string }
    | { type: 'tool.started'; id: string; tool: string; inputs: Inputs }
    | { type: 'tool.finished'; id: string; tool: string; ok: boolean; message: string; data: unknown }
    | { type: 'approval.requested'; id: string; tool: string; reason: string; inputs: Inputs }
    | { type: 'run.completed'; interrupted: boolean }
    | { type: 'run.error'; code: string; message: string };

export interface RawFrame {
    event: string;
    data: string;
}

/**
 * Splits as many complete frames as the buffer holds. The unfinished tail is returned as `rest`,
 * to be prepended to the next chunk.
 */
export function splitFrames(buffer: string): { frames: RawFrame[]; rest: string } {
    const normalized = buffer.replace(/\r\n/g, '\n');
    const pieces = normalized.split('\n\n');
    const rest = pieces.pop() ?? '';
    const frames: RawFrame[] = [];

    for (const piece of pieces) {
        let event = 'message';
        const dataLines: string[] = [];
        for (const line of piece.split('\n')) {
            if (line.startsWith('event:')) {
                event = line.slice('event:'.length).trim();
            } else if (line.startsWith('data:')) {
                dataLines.push(line.slice('data:'.length).replace(/^ /, ''));
            }
        }
        if (dataLines.length > 0) {
            frames.push({ event, data: dataLines.join('\n') });
        }
    }

    return { frames, rest };
}

/**
 * Turns a frame into a typed event, or null when it is not a version-1 event this widget knows.
 */
export function toAgentEvent(frame: RawFrame): AgentEvent | null {
    let payload: Record<string, unknown>;
    try {
        const parsed: unknown = JSON.parse(frame.data);
        if (typeof parsed !== 'object' || parsed === null) {
            return null;
        }
        payload = parsed as Record<string, unknown>;
    } catch {
        return null;
    }

    if (payload.v !== PROTOCOL_VERSION) {
        return null;
    }

    switch (frame.event) {
        case 'text.delta':
            return typeof payload.text === 'string' ? { type: 'text.delta', text: payload.text } : null;
        case 'tool.started':
            return isString(payload.id) && isString(payload.tool)
                ? { type: 'tool.started', id: payload.id, tool: payload.tool, inputs: asInputs(payload.inputs) }
                : null;
        case 'tool.finished':
            return isString(payload.id) && isString(payload.tool)
                ? {
                      type: 'tool.finished',
                      id: payload.id,
                      tool: payload.tool,
                      ok: payload.ok === true,
                      message: isString(payload.message) ? payload.message : '',
                      data: payload.data,
                  }
                : null;
        case 'approval.requested':
            return isString(payload.id) && isString(payload.tool)
                ? {
                      type: 'approval.requested',
                      id: payload.id,
                      tool: payload.tool,
                      reason: isString(payload.reason) ? payload.reason : '',
                      inputs: asInputs(payload.inputs),
                  }
                : null;
        case 'run.completed':
            return { type: 'run.completed', interrupted: payload.interrupted === true };
        case 'run.error':
            return {
                type: 'run.error',
                code: isString(payload.code) ? payload.code : 'error',
                message: isString(payload.message) ? payload.message : 'The assistant failed.',
            };
        default:
            return null;
    }
}

/**
 * Reads a response body as a stream of agent events. Ends when the body ends.
 */
export async function* readAgentEvents(body: ReadableStream<Uint8Array>): AsyncGenerator<AgentEvent> {
    const reader = body.getReader();
    const decoder = new TextDecoder();
    let rest = '';

    try {
        for (;;) {
            const { value, done } = await reader.read();
            if (done) {
                break;
            }
            const split = splitFrames(rest + decoder.decode(value, { stream: true }));
            rest = split.rest;
            for (const frame of split.frames) {
                const event = toAgentEvent(frame);
                if (event !== null) {
                    yield event;
                }
            }
        }

        const tail = splitFrames(rest + decoder.decode());
        for (const frame of [...tail.frames]) {
            const event = toAgentEvent(frame);
            if (event !== null) {
                yield event;
            }
        }
    } finally {
        reader.releaseLock();
    }
}

function isString(value: unknown): value is string {
    return typeof value === 'string';
}

function asInputs(value: unknown): Inputs {
    return typeof value === 'object' && value !== null && !Array.isArray(value) ? (value as Inputs) : {};
}
