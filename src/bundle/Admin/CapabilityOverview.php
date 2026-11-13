<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Admin;

use Masilia\Bundle\IbexaAi\Os\Access\CapabilityAccess;
use Masilia\IbexaAi\Os\Capability;
use Masilia\IbexaAi\Os\CapabilityRegistry;
use Masilia\IbexaAi\Os\Risk;
use Masilia\IbexaAi\Os\Skill;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * What the assistant can do, for the page of the admin that says it: the capabilities by domain, which are turned on,
 * what the current user may use of them and why not, and which packages brought them.
 */
final readonly class CapabilityOverview
{
    /**
     * @param iterable<Skill> $skills
     */
    public function __construct(
        private CapabilityRegistry $registry,
        private CapabilityAccess $access,
        #[AutowireIterator('masilia.ibexa_ai.skill')]
        private iterable $skills = [],
    ) {
    }

    /**
     * @return array{
     *     domains: list<array{
     *         name: string,
     *         enabled: bool,
     *         packages: list<string>,
     *         usable: int,
     *         risks: array<string, int>,
     *         capabilities: list<array{name: string, summary: string, risk: string, package: string, usable: bool, denial: string|null, requires: string}>
     *     }>,
     *     skills: list<array{name: string, package: string}>,
     *     totals: array{capabilities: int, domains: int, enabled_domains: int, usable: int}
     * }
     */
    public function build(): array
    {
        $domains = [];
        foreach ($this->registry->all() as $capability) {
            $name = CapabilityRegistry::domainOf($capability);
            $domains[$name]['capabilities'][] = $capability;
        }
        ksort($domains);

        $rows = [];
        $total = 0;
        $enabledDomains = 0;
        $usableTotal = 0;
        foreach ($domains as $name => $entry) {
            /** @var list<Capability> $capabilities */
            $capabilities = $entry['capabilities'];
            usort($capabilities, static fn (Capability $a, Capability $b): int => $a->name() <=> $b->name());

            $enabled = $this->access->domainEnabled($capabilities[0]);
            $risks = [];
            $usable = 0;
            $list = [];
            foreach ($capabilities as $capability) {
                $denial = $this->access->denial($capability);
                $risks[$capability->risk()->value] = ($risks[$capability->risk()->value] ?? 0) + 1;
                $usable += $denial === null ? 1 : 0;
                $list[] = [
                    'name' => $capability->name(),
                    'summary' => $capability->summary(),
                    'risk' => $capability->risk()->value,
                    'package' => self::packageOf($capability),
                    'usable' => $denial === null,
                    'denial' => $denial,
                    'requires' => implode(', ', array_map(static fn (array $policy): string => $policy[0] . '/' . $policy[1], $this->access->required($capability))),
                ];
            }

            $rows[] = [
                'name' => $name,
                'enabled' => $enabled,
                'packages' => array_values(array_unique(array_column($list, 'package'))),
                'usable' => $usable,
                'risks' => self::orderedRisks($risks),
                'capabilities' => $list,
            ];
            $total += count($list);
            $enabledDomains += $enabled ? 1 : 0;
            $usableTotal += $usable;
        }

        $skills = [];
        foreach ($this->skills as $skill) {
            $skills[] = ['name' => $skill->name(), 'package' => self::packageOf($skill)];
        }

        return [
            'domains' => $rows,
            'skills' => $skills,
            'totals' => ['capabilities' => $total, 'domains' => count($rows), 'enabled_domains' => $enabledDomains, 'usable' => $usableTotal],
        ];
    }

    /**
     * The package an object comes from, by its namespace: "AI OS" for the bundle itself, else the bundle's namespace.
     */
    public static function packageOf(object $object): string
    {
        $class = $object::class;
        if (str_starts_with($class, 'Masilia\\Bundle\\IbexaAi\\')) {
            return 'AI OS';
        }
        $parts = explode('\\', $class);

        return count($parts) > 2 ? $parts[0] . '\\' . $parts[1] . '\\' . $parts[2] : $class;
    }

    /**
     * @param array<string, int> $risks
     *
     * @return array<string, int>
     */
    private static function orderedRisks(array $risks): array
    {
        $ordered = [];
        foreach (Risk::cases() as $risk) {
            if (isset($risks[$risk->value])) {
                $ordered[$risk->value] = $risks[$risk->value];
            }
        }

        return $ordered;
    }
}
