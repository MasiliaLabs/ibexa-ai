import type { Model, Provider } from './types';

export type Errors = Record<string, string>;

export interface ProviderDraft {
    id: number | null;
    name: string;
    identifier: string;
    /** Empty keeps the stored key. */
    apiKey: string;
    apiUrl: string;
    siteaccesses: string[];
}

export interface ModelDraft {
    id: number | null;
    providerId: number | null;
    name: string;
    identifier: string;
    temperature: string;
    maxTokens: string;
    supportsImage: boolean;
    supportsVision: boolean;
}

export function emptyProviderDraft(identifier = 'openai', siteaccesses: string[] = []): ProviderDraft {
    return { id: null, name: '', identifier, apiKey: '', apiUrl: '', siteaccesses };
}

export function providerToDraft(provider: Provider): ProviderDraft {
    return {
        id: provider.id,
        name: provider.name,
        identifier: provider.identifier,
        apiKey: '',
        apiUrl: provider.apiUrl ?? '',
        siteaccesses: provider.siteaccesses,
    };
}

function isHttpUrl(value: string): boolean {
    try {
        const url = new URL(value);

        return url.protocol === 'http:' || url.protocol === 'https:';
    } catch {
        return false;
    }
}

export function validateProvider(draft: ProviderDraft, requiresApiKey: boolean, hasStoredKey: boolean): Errors {
    const errors: Errors = {};
    if (draft.name.trim() === '') {
        errors.name = 'Enter a name.';
    }
    if (draft.apiUrl.trim() !== '' && !isHttpUrl(draft.apiUrl.trim())) {
        errors.apiUrl = 'Enter a full address starting with http:// or https://.';
    }
    if (requiresApiKey && draft.apiKey.trim() === '' && !hasStoredKey) {
        errors.apiKey = 'Enter the API key of this provider.';
    }

    return errors;
}

/** The body sent for a provider. `siteaccesses` is sent only when creating: the card edits them afterwards. */
export function providerPayload(draft: ProviderDraft): Record<string, unknown> {
    const payload: Record<string, unknown> = {
        id: draft.id,
        name: draft.name.trim(),
        identifier: draft.identifier,
        apiKey: draft.apiKey.trim(),
        apiUrl: draft.apiUrl.trim(),
    };
    if (draft.id === null) {
        payload.siteaccesses = draft.siteaccesses;
    }

    return payload;
}

export function emptyModelDraft(providerId: number | null = null): ModelDraft {
    return { id: null, providerId, name: '', identifier: '', temperature: '0.7', maxTokens: '2048', supportsImage: false, supportsVision: false };
}

export function modelToDraft(model: Model): ModelDraft {
    return {
        id: model.id,
        providerId: model.providerId,
        name: model.name,
        identifier: model.identifier,
        temperature: String(model.temperature),
        maxTokens: String(model.maxTokens),
        supportsImage: model.supportsImage,
        supportsVision: model.supportsVision,
    };
}

export function validateModel(draft: ModelDraft): Errors {
    const errors: Errors = {};
    if (draft.providerId === null) {
        errors.providerId = 'Choose a provider.';
    }
    if (draft.name.trim() === '') {
        errors.name = 'Enter a name.';
    }
    if (draft.identifier.trim() === '') {
        errors.identifier = 'Enter the model identifier used by the provider API.';
    }

    const temperature = Number(draft.temperature);
    if (draft.temperature.trim() === '' || Number.isNaN(temperature) || temperature < 0 || temperature > 2) {
        errors.temperature = 'Enter a number between 0 and 2.';
    }

    const maxTokens = Number(draft.maxTokens);
    if (draft.maxTokens.trim() === '' || !Number.isInteger(maxTokens) || maxTokens < 1) {
        errors.maxTokens = 'Enter a whole number, 1 or more.';
    }

    return errors;
}

export function modelPayload(draft: ModelDraft): Record<string, unknown> {
    return {
        id: draft.id,
        providerId: draft.providerId,
        name: draft.name.trim(),
        identifier: draft.identifier.trim(),
        temperature: Number(draft.temperature),
        maxTokens: Number(draft.maxTokens),
        supportsImage: draft.supportsImage,
        supportsVision: draft.supportsVision,
    };
}
