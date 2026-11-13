<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Os\Access;

use Ibexa\Contracts\Core\Repository\PermissionResolver;
use Masilia\IbexaAi\Os\Capability;
use Masilia\IbexaAi\Os\CapabilityRegistry;
use Masilia\IbexaAi\Os\Risk;

/**
 * Whether the current user may use a capability: its domain is on, and the user has the Ibexa policies of what it
 * touches, the permission of its risk, and the one of the content model for the "structure" domain.
 *
 * It decides what is shown to the model. The repository stays the authority: it refuses again what it must.
 */
final readonly class CapabilityAccess
{
    /**
     * @param array<string, bool> $domains Domains turned on or off in the configuration
     */
    public function __construct(
        private PermissionResolver $permissions,
        private array $domains,
    ) {
    }

    public function domainEnabled(Capability $capability): bool
    {
        return ($this->domains[CapabilityRegistry::domainOf($capability)] ?? true) === true;
    }

    public function allowed(Capability $capability): bool
    {
        return $this->denial($capability) === null;
    }

    /**
     * Why the current user cannot use the capability, in words for the user; null when they can.
     */
    public function denial(Capability $capability): ?string
    {
        if (!$this->domainEnabled($capability)) {
            return sprintf('The "%s" capabilities are turned off.', CapabilityRegistry::domainOf($capability));
        }

        foreach ($this->required($capability) as [$module, $function]) {
            if ($this->permissions->hasAccess($module, $function) === false) {
                return sprintf('You do not have the permission %s/%s.', $module, $function);
            }
        }

        return null;
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    public function required(Capability $capability): array
    {
        $required = $capability->requires();
        if ($capability->risk() !== Risk::READ) {
            $required[] = ['masilia_ai', $capability->risk()->permission()];
        }
        if (CapabilityRegistry::domainOf($capability) === 'structure') {
            $required[] = ['masilia_ai', 'structure'];
        }

        return $required;
    }
}
