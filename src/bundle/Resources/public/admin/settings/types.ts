/**
 * Shapes of the JSON the settings API returns (see AdminSettingsController, ProviderCatalog, UsageReport,
 * HealthChecker, ProviderTester). If the server changes, change them here first.
 */

export interface ProviderType {
    value: string;
    label: string;
    requiresApiKey: boolean;
    /** Endpoint used when none is set, for providers that have one. */
    defaultApiUrl: string | null;
}

export interface Provider {
    id: number;
    name: string;
    identifier: string;
    siteaccesses: string[];
    /** Never the key: a mask when one is stored, null otherwise. */
    apiKey: string | null;
    apiUrl: string | null;
    activeChatModelId: number | null;
    activeImageModelId: number | null;
}

export interface Model {
    id: number;
    providerId: number;
    providerName: string;
    name: string;
    identifier: string;
    temperature: number;
    maxTokens: number;
    supportsImage: boolean;
}

export interface Snapshot {
    providers: Provider[];
    models: Model[];
    providerTypes: ProviderType[];
    siteaccesses: string[];
    currentSiteaccess: string;
}

export type HealthState = 'online' | 'offline' | 'not_configured';

export interface Health {
    state: HealthState;
    providerName: string;
    message: string;
    checkedAt: string;
}

export interface TestResult {
    success: boolean;
    message: string;
    streamTested: boolean;
    streamOk: boolean | null;
}

export interface UsageTotals {
    total: number;
    success: number;
    error: number;
    tokensIn: number;
    tokensOut: number;
    avgLatencyMs: number;
}

export interface ProviderUsage extends UsageTotals {
    providerIdentifier: string;
}

export interface UsageWindow {
    since: string;
    totals: UsageTotals;
    perProvider: ProviderUsage[];
}

export interface Usage {
    windows: Record<string, UsageWindow>;
}
