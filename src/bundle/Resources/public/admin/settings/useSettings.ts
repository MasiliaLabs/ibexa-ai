import { useCallback, useEffect, useRef, useState } from 'react';

import { api } from './api';
import type { ApiResult } from './api';
import { modelPayload, providerPayload } from './forms';
import type { ModelDraft, ProviderDraft } from './forms';
import { notify } from '@masilia-admin-kit/notify';
import type { Health, Snapshot, TestResult } from './types';

const EMPTY: Snapshot = { providers: [], models: [], providerTypes: [], siteaccesses: [], currentSiteaccess: '' };

export interface SettingsController {
    snapshot: Snapshot;
    health: Health | null;
    /** True only for the first load: later refreshes keep the screen as it is. */
    loading: boolean;
    loadError: string | null;
    testing: number | null;
    tests: Record<number, TestResult>;
    reload: () => Promise<void>;
    refreshHealth: () => Promise<void>;
    /** The engine status is being checked (the button of the banner turns meanwhile) */
    checking: boolean;
    /** Saves a provider. Resolves to an error message to show in the form, or null on success. */
    saveProvider: (draft: ProviderDraft) => Promise<string | null>;
    deleteProvider: (id: number) => Promise<void>;
    testProvider: (id: number) => Promise<void>;
    setSiteaccesses: (providerId: number, siteaccesses: string[]) => Promise<void>;
    setChatModel: (providerId: number, modelId: number | null) => Promise<void>;
    setImageModel: (providerId: number, modelId: number | null) => Promise<void>;
    saveModel: (draft: ModelDraft) => Promise<string | null>;
    deleteModel: (id: number) => Promise<void>;
}

/**
 * Data and actions of the settings screen. Every write refreshes the lists and the engine status; failures are
 * shown with the server's own message.
 */
export function useSettings(): SettingsController {
    const [snapshot, setSnapshot] = useState<Snapshot>(EMPTY);
    const [health, setHealth] = useState<Health | null>(null);
    const [loading, setLoading] = useState(true);
    const [checking, setChecking] = useState(false);
    const [loadError, setLoadError] = useState<string | null>(null);
    const [testing, setTesting] = useState<number | null>(null);
    const [tests, setTests] = useState<Record<number, TestResult>>({});
    const mounted = useRef(true);

    useEffect(() => {
        mounted.current = true;

        return () => {
            mounted.current = false;
        };
    }, []);

    const refreshHealth = useCallback(async () => {
        setChecking(true);
        // A fast answer would be gone before it is seen: the animation lasts a little at least.
        const [result] = await Promise.all([api.health(), new Promise((resolve) => setTimeout(resolve, 700))]);
        if (mounted.current) {
            if (result.ok) {
                setHealth(result.data);
            }
            setChecking(false);
        }
    }, []);

    const reload = useCallback(async () => {
        const result = await api.snapshot();
        if (!mounted.current) {
            return;
        }
        if (result.ok) {
            setSnapshot(result.data);
            setLoadError(null);
        } else {
            setLoadError(result.message);
        }
        setLoading(false);
    }, []);

    useEffect(() => {
        void reload();
        void refreshHealth();
    }, [reload, refreshHealth]);

    /** Runs a write; on success refreshes everything, on failure tells the user. Resolves to the error or null. */
    const commit = useCallback(async (call: Promise<ApiResult<unknown>>, success: string | null): Promise<string | null> => {
        const result = await call;
        if (!result.ok) {
            notify('error', result.message);
            await reload();

            return result.message;
        }
        if (success !== null) {
            notify('success', success);
        }
        await Promise.all([reload(), refreshHealth()]);

        return null;
    }, [reload, refreshHealth]);

    const saveProvider = useCallback(async (draft: ProviderDraft) => {
        const result = await api.saveProvider(providerPayload(draft));
        if (!result.ok) {
            return result.message;
        }
        notify('success', 'Provider saved.');
        await Promise.all([reload(), refreshHealth()]);

        return null;
    }, [reload, refreshHealth]);

    const saveModel = useCallback(async (draft: ModelDraft) => {
        const result = await api.saveModel(modelPayload(draft));
        if (!result.ok) {
            return result.message;
        }
        notify('success', 'Model saved.');
        await Promise.all([reload(), refreshHealth()]);

        return null;
    }, [reload, refreshHealth]);

    const deleteProvider = useCallback(async (id: number) => {
        await commit(api.deleteProvider(id), 'Provider deleted.');
    }, [commit]);

    const deleteModel = useCallback(async (id: number) => {
        await commit(api.deleteModel(id), 'Model deleted.');
    }, [commit]);

    const testProvider = useCallback(async (id: number) => {
        setTesting(id);
        setTests((previous) => {
            const next = { ...previous };
            delete next[id];

            return next;
        });
        const result = await api.testProvider(id);
        if (!mounted.current) {
            return;
        }
        setTests((previous) => ({ ...previous, [id]: result }));
        setTesting(null);
        void refreshHealth();
    }, [refreshHealth]);

    /** Shows the choice at once, then saves it; a failure restores the server's state. */
    const assign = useCallback(async (apply: (snapshot: Snapshot) => Snapshot, call: Promise<ApiResult<unknown>>) => {
        setSnapshot(apply);
        await commit(call, null);
    }, [commit]);

    const setSiteaccesses = useCallback((providerId: number, siteaccesses: string[]) => assign(
        (current) => ({ ...current, providers: current.providers.map((p) => (p.id === providerId ? { ...p, siteaccesses } : p)) }),
        api.setSiteaccesses(providerId, siteaccesses),
    ), [assign]);

    const setChatModel = useCallback((providerId: number, modelId: number | null) => assign(
        (current) => ({ ...current, providers: current.providers.map((p) => (p.id === providerId ? { ...p, activeChatModelId: modelId } : p)) }),
        api.setChatModel(providerId, modelId),
    ), [assign]);

    const setImageModel = useCallback((providerId: number, modelId: number | null) => assign(
        (current) => ({ ...current, providers: current.providers.map((p) => (p.id === providerId ? { ...p, activeImageModelId: modelId } : p)) }),
        api.setImageModel(providerId, modelId),
    ), [assign]);

    return {
        snapshot, health, loading, loadError, testing, tests, checking,
        reload, refreshHealth, saveProvider, deleteProvider, testProvider,
        setSiteaccesses, setChatModel, setImageModel, saveModel, deleteModel,
    };
}
