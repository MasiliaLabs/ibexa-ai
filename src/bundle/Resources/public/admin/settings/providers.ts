import type { Health, HealthState, Model, Provider, ProviderType } from './types';

export function providerLabel(types: ProviderType[], identifier: string): string {
    return types.find((type) => type.value === identifier)?.label ?? identifier;
}

export function modelsOf(models: Model[], providerId: number): Model[] {
    return models.filter((model) => model.providerId === providerId);
}

export function modelOptionLabel(model: Model): string {
    return `${model.name} (${model.identifier})`;
}

/**
 * Providers matching a search: by name, type, siteaccess, or by one of their models. `modelIds` tells which
 * models matched so the list can open those providers.
 */
export function searchProviders(
    providers: Provider[],
    models: Model[],
    types: ProviderType[],
    query: string,
): { providers: Provider[]; matchingProviderIds: Set<number> } {
    const needle = query.trim().toLowerCase();
    if (needle === '') {
        return { providers, matchingProviderIds: new Set() };
    }

    const byModel = new Set<number>();
    for (const model of models) {
        if (`${model.name} ${model.identifier}`.toLowerCase().includes(needle)) {
            byModel.add(model.providerId);
        }
    }

    const matching = providers.filter((provider) => {
        const text = `${provider.name} ${provider.identifier} ${providerLabel(types, provider.identifier)} ${provider.siteaccesses.join(' ')}`;

        return text.toLowerCase().includes(needle) || byModel.has(provider.id);
    });

    return { providers: matching, matchingProviderIds: byModel };
}

export interface ActiveModel {
    provider: Provider;
    model: Model;
}

/** The provider and model answering for a siteaccess, for chat or for images. */
export function activeFor(kind: 'chat' | 'image', siteaccess: string, providers: Provider[], models: Model[]): ActiveModel | null {
    for (const provider of providers) {
        const modelId = kind === 'chat' ? provider.activeChatModelId : provider.activeImageModelId;
        if (modelId === null || !provider.siteaccesses.includes(siteaccess)) {
            continue;
        }
        const model = models.find((candidate) => candidate.id === modelId);
        if (model !== undefined) {
            return { provider, model };
        }
    }

    return null;
}

export interface EngineSummary {
    state: HealthState | 'unknown';
    chat: ActiveModel | null;
    image: ActiveModel | null;
    /** What the administrator should do next, when something is missing. */
    guidance: string | null;
    message: string;
}

/**
 * What the banner says about the current siteaccess. The state is the server's (it knows the configuration
 * fallbacks and the recent errors); the guidance explains what is missing from the lists the page already has.
 */
export function describeEngine(input: {
    providers: Provider[];
    models: Model[];
    siteaccess: string;
    health: Health | null;
}): EngineSummary {
    const { providers, models, siteaccess, health } = input;
    const chat = activeFor('chat', siteaccess, providers, models);
    const image = activeFor('image', siteaccess, providers, models);
    const state: EngineSummary['state'] = health?.state ?? 'unknown';

    let guidance: string | null = null;
    if (state === 'offline') {
        guidance = 'Check the API key and the endpoint of this provider, then test the connection.';
    } else if (chat === null) {
        const assigned = providers.filter((provider) => provider.siteaccesses.includes(siteaccess));
        guidance = assigned.length === 0
            ? `No provider is assigned to ${siteaccess}. Assign one from its card, then choose a chat model.`
            : `${assigned.length === 1 ? 'The provider assigned' : 'The providers assigned'} to ${siteaccess} ${assigned.length === 1 ? 'has' : 'have'} no chat model. Choose one on the provider card.`;
    }

    return { state, chat, image, guidance, message: health?.message ?? '' };
}

export type CellState = 'unassigned' | 'ready' | 'no_model';

/** One cell of the siteaccess x provider matrix. */
export function cellState(provider: Provider, siteaccess: string): CellState {
    if (!provider.siteaccesses.includes(siteaccess)) {
        return 'unassigned';
    }

    return provider.activeChatModelId === null ? 'no_model' : 'ready';
}
