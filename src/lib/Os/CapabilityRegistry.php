<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Os;

use InvalidArgumentException;

/**
 * The capabilities there are, and how to find one from words. It knows nothing about the user: whether a capability
 * is allowed is decided by whoever asks (the $allowed filter).
 */
final class CapabilityRegistry
{
    /** @var array<string, Capability> */
    private array $capabilities = [];

    /**
     * @param iterable<Capability> $capabilities
     */
    public function __construct(iterable $capabilities)
    {
        foreach ($capabilities as $capability) {
            $name = $capability->name();
            if (preg_match('/^[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*)+$/', $name) !== 1) {
                throw new InvalidArgumentException(sprintf('Capability name "%s" must be dotted and lower case, like "content.update".', $name));
            }
            if (isset($this->capabilities[$name])) {
                throw new InvalidArgumentException(sprintf('Capability "%s" is registered twice.', $name));
            }
            $this->capabilities[$name] = $capability;
        }
        ksort($this->capabilities);
    }

    public static function domainOf(Capability $capability): string
    {
        return explode('.', $capability->name(), 2)[0];
    }

    public function get(string $name): ?Capability
    {
        return $this->capabilities[$name] ?? null;
    }

    /**
     * @return array<string, Capability> By name
     */
    public function all(): array
    {
        return $this->capabilities;
    }

    /**
     * The domains and how many capabilities each has, among those $allowed.
     *
     * @param (callable(Capability): bool)|null $allowed
     *
     * @return array<string, int>
     */
    public function domains(?callable $allowed = null): array
    {
        $domains = [];
        foreach ($this->capabilities as $capability) {
            if ($allowed === null || $allowed($capability)) {
                $domain = self::domainOf($capability);
                $domains[$domain] = ($domains[$domain] ?? 0) + 1;
            }
        }
        ksort($domains);

        return $domains;
    }

    /**
     * The capabilities that answer best to the words, best first. Without words, all of the domain, by name.
     *
     * @param (callable(Capability): bool)|null $allowed
     *
     * @return list<Capability>
     */
    public function search(string $query = '', ?string $domain = null, ?callable $allowed = null, int $limit = 12): array
    {
        $words = array_values(array_filter(preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($query)) ?: [], static fn (string $word): bool => mb_strlen($word) >= 2));

        $scored = [];
        foreach ($this->capabilities as $capability) {
            if (($domain !== null && self::domainOf($capability) !== $domain) || ($allowed !== null && !$allowed($capability))) {
                continue;
            }
            $score = $words === [] ? 1 : self::score($capability, $words);
            if ($score > 0) {
                $scored[] = [$score, $capability];
            }
        }
        usort($scored, static fn (array $a, array $b): int => [$b[0], $a[1]->name()] <=> [$a[0], $b[1]->name()]);

        return array_map(static fn (array $row): Capability => $row[1], array_slice($scored, 0, max(1, $limit)));
    }

    /**
     * @param list<string> $words
     */
    private static function score(Capability $capability, array $words): int
    {
        $name = mb_strtolower($capability->name());
        $summary = mb_strtolower($capability->summary());
        $description = mb_strtolower($capability->description());

        $score = 0;
        foreach ($words as $word) {
            $score += (str_contains($name, $word) ? 5 : 0) + (str_contains($summary, $word) ? 3 : 0) + (str_contains($description, $word) ? 1 : 0);
        }

        return $score;
    }
}
