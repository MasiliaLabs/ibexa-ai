/**
 * What the user reads of a tool's answer. Tools answer with a JSON object {success, message, data}; Neuron answers
 * a rejected or skipped call with its own text, which must not look like a success. The stream carries the answer
 * as it is: it is read here.
 */

const NOT_EXECUTED_PREFIX = 'TOOL NOT EXECUTED';
const MAX_MESSAGE_LENGTH = 500;

export interface ToolOutcome {
    ok: boolean;
    message: string;
}

function clip(text: string): string {
    return text.length > MAX_MESSAGE_LENGTH ? `${text.slice(0, MAX_MESSAGE_LENGTH)}…` : text;
}

export function toolOutcome(raw: unknown): ToolOutcome {
    if (typeof raw !== 'string') {
        // Already structured (some runtimes parse the answer): the same fields apply.
        if (typeof raw === 'object' && raw !== null && !Array.isArray(raw)) {
            const record = raw as Record<string, unknown>;

            return { ok: record.success === true, message: clip(typeof record.message === 'string' ? record.message : '') };
        }

        return { ok: true, message: '' };
    }

    if (raw.startsWith(NOT_EXECUTED_PREFIX)) {
        return { ok: false, message: 'Not run: the change was rejected.' };
    }

    try {
        const decoded: unknown = JSON.parse(raw);
        if (typeof decoded === 'object' && decoded !== null && !Array.isArray(decoded)) {
            const record = decoded as Record<string, unknown>;

            return { ok: record.success === true, message: clip(typeof record.message === 'string' ? record.message : '') };
        }
    } catch {
        // Not JSON: plain text.
    }

    return { ok: true, message: clip(raw) };
}
