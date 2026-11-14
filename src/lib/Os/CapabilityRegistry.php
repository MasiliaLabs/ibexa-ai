<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Os;

use Masilia\Contracts\IbexaAi\Os\Capability;
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
}
