import { csrfHeaders, route } from '@masilia-admin-kit/admin';
import type { Health, Snapshot, TestResult, Usage } from './types';

/** Outcome of a call: HTTP errors are values, not exceptions, so a screen always has a message to show. */
export type ApiResult<T> =
    | { ok: true; data: T }
    | { ok: false; status: number; message: string; body: unknown };

export type Fetcher = (input: string, init?: RequestInit) => Promise<Response>;

export const routes = {
    data: () => route('masilia.ibexa_ai.admin.data'),
    health: () => route('masilia.ibexa_ai.admin.health'),
    usage: () => route('masilia.ibexa_ai.admin.usage'),
    provider: () => route('masilia.ibexa_ai.admin.provider_save'),
    providerById: (id: number) => route('masilia.ibexa_ai.admin.provider_delete', { id }),
    siteaccesses: (id: number) => route('masilia.ibexa_ai.admin.provider_siteaccesses', { id }),
    chatModel: (id: number) => route('masilia.ibexa_ai.admin.chat_model', { id }),
    imageModel: (id: number) => route('masilia.ibexa_ai.admin.image_model', { id }),
    test: (id: number) => `${route('masilia.ibexa_ai.admin.provider_test', { id })}?stream=1`,
    model: () => route('masilia.ibexa_ai.admin.model_save'),
    modelById: (id: number) => route('masilia.ibexa_ai.admin.model_delete', { id }),
};

function isRecord(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}

/**
 * The message of an error body. The server answers {"error": {"code", "message"}}; a plain string error or a
 * top-level message are accepted too. Anything else gives the fallback, never "[object Object]".
 */
export function messageFromBody(body: unknown, fallback: string): string {
    if (isRecord(body)) {
        const error = body.error;
        if (isRecord(error) && typeof error.message === 'string' && error.message !== '') {
            return error.message;
        }
        if (typeof error === 'string' && error !== '') {
            return error;
        }
        if (typeof body.message === 'string' && body.message !== '') {
            return body.message;
        }
    }

    return fallback;
}

/**
 * Providers often put a JSON document in their error text ("HTTP 401: {"error": {"message": ...}}"). Keeps the
 * readable part.
 */
export function cleanProviderMessage(message: string): string {
    const text = message.trim();
    if (text === '') {
        return 'Unknown error.';
    }

    const start = text.indexOf('{');
    if (start === -1) {
        return text;
    }

    const prefix = text.slice(0, start).trim();
    try {
        const parsed: unknown = JSON.parse(text.slice(start));
        if (isRecord(parsed)) {
            const inner = isRecord(parsed.error) && typeof parsed.error.message === 'string'
                ? parsed.error.message
                : typeof parsed.message === 'string' ? parsed.message : '';
            if (inner !== '') {
                return `${prefix} ${inner}`.trim();
            }
        }
    } catch {
        // not JSON: keep the text as it is
    }

    return text;
}

export async function request<T>(url: string, init?: RequestInit, fetcher: Fetcher = (u, i) => fetch(u, i)): Promise<ApiResult<T>> {
    let response: Response;
    try {
        response = await fetcher(url, { ...init, headers: csrfHeaders({ Accept: 'application/json', ...(init?.headers as Record<string, string> | undefined) }) });
    } catch {
        return { ok: false, status: 0, message: 'Network error. Check your connection and try again.', body: null };
    }

    let body: unknown = null;
    try {
        body = await response.json();
    } catch {
        body = null;
    }

    if (!response.ok) {
        return { ok: false, status: response.status, message: messageFromBody(body, `The server answered ${response.status}.`), body };
    }

    return { ok: true, data: body as T };
}

function send(method: string, payload: unknown): RequestInit {
    return { method, headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) };
}

export const api = {
    snapshot: () => request<Snapshot>(routes.data()),
    health: () => request<Health>(routes.health()),
    usage: () => request<Usage>(routes.usage()),
    saveProvider: (payload: unknown) => request<{ success: true }>(routes.provider(), send('POST', payload)),
    deleteProvider: (id: number) => request<{ success: true }>(routes.providerById(id), { method: 'DELETE' }),
    setSiteaccesses: (id: number, siteaccesses: string[]) =>
        request<{ success: true }>(routes.siteaccesses(id), send('PUT', { siteaccesses })),
    setChatModel: (id: number, modelId: number | null) =>
        request<{ success: true }>(routes.chatModel(id), send('PUT', { modelId })),
    setImageModel: (id: number, modelId: number | null) =>
        request<{ success: true }>(routes.imageModel(id), send('PUT', { modelId })),
    saveModel: (payload: unknown) => request<{ success: true }>(routes.model(), send('POST', payload)),
    deleteModel: (id: number) => request<{ success: true }>(routes.modelById(id), { method: 'DELETE' }),
    /** The test endpoint answers 400 with a result body when the connection fails: that body is the result. */
    testProvider: async (id: number): Promise<TestResult> => {
        const result = await request<TestResult>(routes.test(id), { method: 'POST' });
        if (result.ok) {
            return result.data;
        }

        const body = result.body;
        const message = isRecord(body) && typeof body.message === 'string' ? body.message : result.message;

        return { success: false, message: cleanProviderMessage(message), streamTested: false, streamOk: null };
    },
};
