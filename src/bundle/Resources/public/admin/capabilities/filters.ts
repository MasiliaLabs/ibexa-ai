import type { CapabilityDto, DomainDto } from './types';

export interface Filters {
    query: string;
    risk: string;
    domain: string;
    /** 'usable' keeps what the person can use; '' keeps everything */
    availability: string;
}

export const NO_FILTERS: Filters = { query: '', risk: '', domain: '', availability: '' };

export type RiskTagType = 'info' | 'warning' | 'error' | 'primary-alt';

/** The design system's tag type of a risk: reading is calm, writing warns, destroying is an error, access is its own colour. */
export function riskTagType(risk: string): RiskTagType {
    switch (risk) {
        case 'write':
            return 'warning';
        case 'destructive':
            return 'error';
        case 'access':
            return 'primary-alt';
        default:
            return 'info';
    }
}

export function isFiltering(filters: Filters): boolean {
    return filters.query.trim() !== '' || filters.risk !== '' || filters.domain !== '' || filters.availability !== '';
}

/** What a capability says about itself, in lower case, for the search: its name, what it does and its risk. */
function haystack(capability: CapabilityDto): string {
    return `${capability.name} ${capability.summary} ${capability.risk}`.toLowerCase();
}

export function matchesCapability(capability: CapabilityDto, filters: Filters): boolean {
    const words = filters.query.toLowerCase().split(/\s+/).filter(Boolean);
    const text = haystack(capability);

    return words.every((word) => text.includes(word))
        && (filters.risk === '' || capability.risk === filters.risk)
        && (filters.availability !== 'usable' || capability.usable);
}

/** The domains with only the capabilities that match; a domain with none left is not shown. */
export function filterDomains(domains: readonly DomainDto[], filters: Filters): DomainDto[] {
    return domains
        .filter((domain) => filters.domain === '' || domain.name === filters.domain)
        .map((domain) => ({ ...domain, capabilities: domain.capabilities.filter((capability) => matchesCapability(capability, filters)) }))
        .filter((domain) => domain.capabilities.length > 0);
}

/** A domain starts open when it is on (the access domain is long and rarely wanted), and opens whenever the person is searching it. */
export function startsOpen(domain: DomainDto, filters: Filters): boolean {
    return isFiltering(filters) || (domain.enabled && domain.name !== 'access');
}

export function countCapabilities(domains: readonly DomainDto[]): number {
    return domains.reduce((total, domain) => total + domain.capabilities.length, 0);
}

/** The rights a capability needs, as one line ("—" when none). */
export function needsOf(requires: string | string[] | null): string {
    const list = Array.isArray(requires) ? requires : requires === null ? [] : [requires];
    const text = list.filter((entry) => entry !== '').join(', ');

    return text === '' ? '—' : text;
}

/** The risks present in the domains, in the order of the severity (for the filter). */
export function risksOf(domains: readonly DomainDto[]): string[] {
    const order = ['read', 'write', 'destructive', 'access'];
    const present = new Set(domains.flatMap((domain) => domain.capabilities.map((capability) => capability.risk as string)));

    return [...order.filter((risk) => present.has(risk)), ...[...present].filter((risk) => !order.includes(risk)).sort()];
}
