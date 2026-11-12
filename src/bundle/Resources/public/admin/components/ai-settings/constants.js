/**
 * Apply-mode constants: how the AI response is distributed onto the DOM.
 * - WHOLE_BLOCK: parse a JSON object and distribute values to all matching inputs.
 * - SUB_FIELD:   extract one value for a single targeted input.
 */
export const APPLY_MODE = Object.freeze({
    WHOLE_BLOCK: 'whole-block',
    SUB_FIELD: 'sub-field',
});

/**
 * Suggest-mode constants: how the generated text is merged with existing content.
 */
export const SUGGEST_MODE = Object.freeze({
    REPLACE: 'replace',
    APPEND: 'append',
});

/**
 * Shared constants for AI Settings dashboard.
 * Single source of truth for provider types — used in filters, drawers, and cards.
 */
export const PROVIDER_TYPES = [
    { value: 'openai',    label: 'OpenAI' },
    { value: 'anthropic', label: 'Anthropic' },
    { value: 'ollama',    label: 'Ollama' },
    { value: 'mistral',   label: 'Mistral' },
    { value: 'minimax',   label: 'MiniMax' },
    { value: 'qwen',      label: 'Qwen' },
    { value: 'gemini',    label: 'Google Gemini' },
];

export function getProviderLabel(identifier) {
    const found = PROVIDER_TYPES.find(t => t.value === identifier);
    return found ? found.label : identifier;
}

/**
 * Dispatch an 'ibexa-notify' custom event on document.body.
 * This triggers the built-in Ibexa Admin notification system.
 * @param {('success'|'error'|'warning'|'info')} type
 * @param {string} message
 */
export function notify(type, message) {
    const label = type === 'danger' ? 'error' : type;
    const event = new CustomEvent('ibexa-notify', {
        detail: { message, label }
    });
    document.body.dispatchEvent(event);
}

/**
 * Format raw error messages, especially OpenAI/Anthropic/Mistral JSON payloads,
 * into a clean, human-readable format.
 * Also handles the structured { error: { code, message } } envelope from the BE.
 * @param {string|object} message
 * @returns {string}
 */
export function cleanErrorMessage(message) {
    if (!message) return 'Unknown error occurred.';

    // Handle structured error envelope from BE: { error: { code, message } }
    if (typeof message === 'object') {
        if (message.error?.message) return message.error.message;
        if (message.message) return message.message;
        return JSON.stringify(message);
    }

    const trimMsg = String(message).trim();

    // Check if entire message is JSON
    if (trimMsg.startsWith('{') || trimMsg.startsWith('[')) {
        try {
            const parsed = JSON.parse(trimMsg);
            if (parsed.error && parsed.error.message) {
                return parsed.error.message;
            }
            if (parsed.message) {
                return parsed.message;
            }
        } catch (e) {
            // fallback
        }
    }

    // Check if there is a JSON part after a prefix (e.g. "API returned HTTP 401: {...}")
    const jsonStart = trimMsg.indexOf('{');
    if (jsonStart !== -1) {
        const prefix = trimMsg.substring(0, jsonStart).trim();
        const jsonPart = trimMsg.substring(jsonStart).trim();
        try {
            const parsed = JSON.parse(jsonPart);
            if (parsed.error && parsed.error.message) {
                return `${prefix} ${parsed.error.message}`;
            }
            if (parsed.message) {
                return `${prefix} ${parsed.message}`;
            }
        } catch (e) {
            // fallback
        }
    }

    return trimMsg;
}


