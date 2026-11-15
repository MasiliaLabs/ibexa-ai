export type Risk = 'read' | 'write' | 'destructive' | 'access';

export interface CapabilityDto {
    name: string;
    summary: string;
    risk: Risk;
    package: string;
    usable: boolean;
    denial: string;
    requires: string | string[] | null;
}

export interface DomainDto {
    name: string;
    enabled: boolean;
    packages: string[];
    usable: number;
    risks: Record<string, number>;
    capabilities: CapabilityDto[];
}

export interface OverviewDto {
    domains: DomainDto[];
    skills: { name: string; package: string }[];
    totals: { capabilities: number; domains: number; enabled_domains: number; usable: number };
}

export interface CoverageDto {
    routes: number;
    covered: number;
    out_of_scope: number;
    planned: number;
    unmapped: string[];
}

export interface ActivityDto {
    when: string;
    user: string;
    capability: string;
    risk: Risk;
    success: boolean;
    message: string;
}

/** What the page receives from the server: coverage and activity only for an administrator. */
export interface PageData {
    overview: OverviewDto;
    coverage: CoverageDto | null;
    activity: ActivityDto[] | null;
}
