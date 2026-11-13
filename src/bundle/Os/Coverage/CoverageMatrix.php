<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Os\Coverage;

use InvalidArgumentException;
use Symfony\Component\Yaml\Yaml;

/**
 * Which capability answers each admin route of Ibexa, or why none should. It is what "done" means for the AI OS: a
 * route nobody mapped is a hole (a new admin screen after an Ibexa update, a bundle installed since).
 *
 * The file has two sections: "capabilities" (route => capability name; the capability may not exist yet, then the route
 * is "planned") and "out_of_scope" (route, or prefix ending in "*", => the reason).
 */
final readonly class CoverageMatrix
{
    /**
     * @param array<string, string> $capabilities route => capability
     * @param array<string, string> $outOfScope   route or "prefix*" => reason
     */
    public function __construct(
        private array $capabilities,
        private array $outOfScope,
    ) {
    }

    public static function fromFile(string $path): self
    {
        $data = Yaml::parseFile($path);
        if (!is_array($data)) {
            throw new InvalidArgumentException(sprintf('%s is not a coverage file.', $path));
        }

        foreach (['capabilities', 'out_of_scope'] as $section) {
            foreach ((array) ($data[$section] ?? []) as $route => $value) {
                if (!is_string($route) || !is_string($value) || $value === '') {
                    throw new InvalidArgumentException(sprintf('%s: "%s" needs a text value.', $section, (string) $route));
                }
            }
        }
        $both = array_intersect_key((array) ($data['capabilities'] ?? []), (array) ($data['out_of_scope'] ?? []));
        if ($both !== []) {
            throw new InvalidArgumentException(sprintf('Mapped and out of scope at once: %s.', implode(', ', array_keys($both))));
        }

        return new self((array) ($data['capabilities'] ?? []), (array) ($data['out_of_scope'] ?? []));
    }

    /**
     * @param list<string>          $routes
     * @param callable(string): bool $exists Whether a capability is registered
     *
     * @return array{
     *     covered: array<string, string>,
     *     planned: array<string, string>,
     *     out_of_scope: array<string, string>,
     *     unmapped: list<string>,
     *     stale: list<string>
     * } routes by outcome; "stale" are entries that match no route (kept apart: a bundle may simply not be installed)
     */
    public function classify(array $routes, callable $exists): array
    {
        $result = ['covered' => [], 'planned' => [], 'out_of_scope' => [], 'unmapped' => [], 'stale' => []];
        $used = [];

        foreach ($routes as $route) {
            if (isset($this->capabilities[$route])) {
                $used[$route] = true;
                $capability = $this->capabilities[$route];
                $result[$exists($capability) ? 'covered' : 'planned'][$route] = $capability;
                continue;
            }
            $reason = $this->reasonFor($route, $key);
            if ($reason !== null) {
                $used[$key] = true;
                $result['out_of_scope'][$route] = $reason;
                continue;
            }
            $result['unmapped'][] = $route;
        }

        foreach (array_merge(array_keys($this->capabilities), array_keys($this->outOfScope)) as $entry) {
            if (!isset($used[$entry])) {
                $result['stale'][] = $entry;
            }
        }

        return $result;
    }

    private function reasonFor(string $route, ?string &$key = null): ?string
    {
        if (isset($this->outOfScope[$route])) {
            $key = $route;

            return $this->outOfScope[$route];
        }
        $best = null;
        foreach ($this->outOfScope as $pattern => $reason) {
            if (str_ends_with($pattern, '*') && str_starts_with($route, substr($pattern, 0, -1)) && ($best === null || strlen($pattern) > strlen($best))) {
                $best = $pattern;
            }
        }
        $key = $best;

        return $best !== null ? $this->outOfScope[$best] : null;
    }
}
