import React, { useMemo, useState } from 'react';

import { FilterCard, FilterSelect, FilterZone } from '@masilia-admin-kit/FilterCard';
import { iconPath } from '@masilia-admin-kit/icons';
import { AssetsProvider, Tag, TranslatorProvider } from '@masilia-admin-kit/ids';
import { SearchInput } from '@masilia-admin-kit/SearchInput';
import { Section } from '@masilia-admin-kit/Section';
import { StatTile } from '@masilia-admin-kit/StatTile';
import { DataTable, TableHeader } from '@masilia-admin-kit/Table';
import type { Column } from '@masilia-admin-kit/Table';
import { Tabs } from '@masilia-admin-kit/Tabs';
import { NO_FILTERS, countCapabilities, filterDomains, needsOf, riskTagType, risksOf, startsOpen } from './filters';
import type { Filters } from './filters';
import { t } from './messages';
import type { ActivityDto, CapabilityDto, CoverageDto, DomainDto, PageData } from './types';

function RiskTag({ risk }: { risk: string }): React.ReactElement {
    return <Tag type={riskTagType(risk)} size="small">{risk}</Tag>;
}

function DomainHeader({ domain, total }: { domain: DomainDto; total: number }): React.ReactElement {
    return (
        <span className="masilia-caps__domain-head">
            <strong>{domain.name}</strong>
            <Tag type={domain.enabled ? 'success' : 'neutral'} size="small">{domain.enabled ? t('domain.on') : t('domain.off')}</Tag>
            {Object.entries(domain.risks).map(([risk, count]) => <Tag key={risk} type={riskTagType(risk)} size="small">{`${count} ${risk}`}</Tag>)}
            <span className="masilia-caps__muted">
                {t('domain.usable', { usable: domain.usable, total })}{domain.packages.length > 0 ? ` · ${domain.packages.join(', ')}` : ''}
            </span>
        </span>
    );
}

const columns: Column<CapabilityDto>[] = [
    { key: 'name', header: t('column.capability'), render: (capability) => <code>{capability.name}</code> },
    { key: 'does', header: t('column.does'), render: (capability) => capability.summary },
    { key: 'risk', header: t('column.risk'), render: (capability) => <RiskTag risk={capability.risk} /> },
    { key: 'needs', header: t('column.needs'), render: (capability) => <span className="masilia-caps__muted">{needsOf(capability.requires)}</span> },
    {
        key: 'you',
        header: t('column.you'),
        render: (capability) => (
            <>
                <Tag type={capability.usable ? 'success' : 'neutral'} size="small">{capability.usable ? t('answer.yes') : t('answer.no')}</Tag>
                {capability.usable ? null : <div className="masilia-caps__muted">{capability.denial}</div>}
            </>
        ),
    },
];

function CapabilitiesTab({ data }: { data: PageData }): React.ReactElement {
    const { overview } = data;
    const [filters, setFilters] = useState<Filters>(NO_FILTERS);
    const shown = useMemo(() => filterDomains(overview.domains, filters), [overview.domains, filters]);
    const set = (change: Partial<Filters>): void => setFilters((current) => ({ ...current, ...change }));
    const risks = useMemo(() => risksOf(overview.domains), [overview.domains]);

    return (
        <>
            {overview.skills.length > 0 ? (
                <div className="masilia-caps__skills">
                    <strong>{t('skills.title')}</strong>
                    {overview.skills.map((skill) => <Tag key={skill.name} type="primary" size="small">{`${skill.name} (${skill.package})`}</Tag>)}
                    <span className="masilia-caps__muted">{t('skills.help')}</span>
                </div>
            ) : null}
            <FilterCard>
                <FilterZone>
                    <SearchInput value={filters.query} onChange={(query) => set({ query })} placeholder={t('list.filter.placeholder')} clearLabel={t('list.filter.clear')} />
                </FilterZone>
                <FilterZone>
                    <FilterSelect label={t('list.filter.risk')} value={filters.risk} emptyLabel={t('list.filter.all')} options={risks.map((risk) => ({ value: risk, label: risk }))} onChange={(risk) => set({ risk })} />
                </FilterZone>
                <FilterZone>
                    <FilterSelect label={t('list.filter.domain')} value={filters.domain} emptyLabel={t('list.filter.all')} options={overview.domains.map((domain) => ({ value: domain.name, label: domain.name }))} onChange={(domain) => set({ domain })} />
                </FilterZone>
                <FilterZone>
                    <FilterSelect label={t('list.filter.availability')} value={filters.availability} emptyLabel={t('list.filter.all')} options={[{ value: 'usable', label: t('list.filter.usable') }]} onChange={(availability) => set({ availability })} />
                </FilterZone>
            </FilterCard>
            <TableHeader headline={t('list.title')} count={countCapabilities(shown)} />
            {shown.length === 0 ? (
                <DataTable columns={columns} rows={[]} rowKey={(capability) => capability.name} empty={{ info: t('list.empty') }} label={t('list.title')} />
            ) : shown.map((domain) => (
                <Section key={domain.name} title={<DomainHeader domain={domain} total={overview.domains.find((full) => full.name === domain.name)?.capabilities.length ?? domain.capabilities.length} />} open={startsOpen(domain, filters)}>
                    {domain.enabled ? null : (
                        <div className="ibexa-alert ibexa-alert--info" role="note">
                            <span>{t('domain.off_note')} <code>{`masilia_ibexa_ai.os.domains.${domain.name}: true`}</code>.</span>
                        </div>
                    )}
                    <DataTable columns={columns} rows={domain.capabilities} rowKey={(capability) => capability.name} empty={{ info: t('list.empty') }} label={domain.name} />
                </Section>
            ))}
        </>
    );
}

function CoverageTab({ coverage }: { coverage: CoverageDto }): React.ReactElement {
    return (
        <>
            <TableHeader headline={t('coverage.title')} />
            <p className="masilia-caps__muted">
                {t('coverage.summary', { routes: coverage.routes, covered: coverage.covered, out: coverage.out_of_scope, planned: coverage.planned, unmapped: coverage.unmapped.length })}
                {' '}{t('coverage.check')} <code>masilia:ai:os:coverage --strict</code>
            </p>
            {coverage.unmapped.length > 0 ? (
                <div className="ibexa-alert ibexa-alert--warning" role="note">
                    <strong>{t('coverage.unmapped.title')}</strong> ({t('coverage.unmapped.why')}): {coverage.unmapped.join(', ')}
                </div>
            ) : <p>{t('coverage.none')}</p>}
        </>
    );
}

const activityColumns: Column<ActivityDto>[] = [
    { key: 'when', header: t('activity.when'), render: (row) => <span className="masilia-caps__muted masilia-caps__nowrap">{row.when}</span> },
    { key: 'who', header: t('activity.who'), render: (row) => row.user },
    { key: 'capability', header: t('column.capability'), render: (row) => <code>{row.capability}</code> },
    { key: 'risk', header: t('column.risk'), render: (row) => <RiskTag risk={row.risk} /> },
    {
        key: 'result',
        header: t('activity.result'),
        render: (row) => (
            <>
                <Tag type={row.success ? 'success' : 'error'} size="small">{row.success ? t('activity.done') : t('activity.failed')}</Tag>
                {row.message !== '' ? <div className="masilia-caps__muted">{row.message}</div> : null}
            </>
        ),
    },
];

/** "What the assistant can do": figures, then tabs for the capabilities, and for an administrator the coverage of the admin and the latest activity. */
export function CapabilitiesApp({ data }: { data: PageData }): React.ReactElement {
    const [tab, setTab] = useState('capabilities');
    const { overview, coverage, activity } = data;
    const tabs = [{ id: 'capabilities', label: t('tab.capabilities'), content: <CapabilitiesTab data={data} /> }];
    if (coverage !== null) {
        tabs.push({ id: 'coverage', label: t('tab.coverage'), content: <CoverageTab coverage={coverage} /> });
    }
    if (activity !== null) {
        tabs.push({
            id: 'activity',
            label: t('tab.activity'),
            content: (
                <>
                    <TableHeader headline={t('activity.title')} count={activity.length} />
                    <DataTable columns={activityColumns} rows={activity} rowKey={(row) => `${row.when}|${row.capability}|${row.user}`} empty={{ info: t('activity.empty') }} label={t('activity.title')} />
                </>
            ),
        });
    }

    return (
        <TranslatorProvider value={{ trans: (id) => t(id) }}>
            <AssetsProvider value={{ getIconPath: iconPath }}>
                <div className="masilia-caps">
                    <p className="masilia-caps__muted">{t('capabilities.intro')}</p>
                    <div className="masilia-kit-stats">
                        <StatTile value={overview.totals.capabilities} label={t('stat.capabilities')} />
                        <StatTile value={overview.totals.domains} label={t('stat.domains', { on: overview.totals.enabled_domains })} />
                        <StatTile value={overview.totals.usable} label={t('stat.usable')} />
                        {coverage !== null ? <StatTile value={`${coverage.covered} / ${coverage.routes - coverage.out_of_scope}`} label={t('stat.covered')} /> : null}
                    </div>
                    <Tabs label={t('capabilities.title')} active={tab} onChange={setTab} tabs={tabs} />
                </div>
            </AssetsProvider>
        </TranslatorProvider>
    );
}
