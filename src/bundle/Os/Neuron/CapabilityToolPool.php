<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Os\Neuron;

use Masilia\Bundle\IbexaAi\Os\Access\CapabilityAccess;
use Masilia\Bundle\IbexaAi\Os\Execution\CapabilityRunner;
use Masilia\Contracts\IbexaAi\Os\Capability;
use Masilia\IbexaAi\Os\CapabilityRegistry;

/** The capabilities the current user may use, as Neuron tools. */
readonly class CapabilityToolPool
{
    public function __construct(
        private CapabilityRegistry $registry,
        private CapabilityAccess $access,
        private CapabilityRunner $runner,
    ) {
    }

    /**
     * @return list<CapabilityTool>
     */
    public function forCurrentUser(): array
    {
        return array_values(array_map(
            fn (Capability $capability): CapabilityTool => new CapabilityTool($capability, $this->runner),
            array_filter($this->registry->all(), fn (Capability $capability): bool => $this->access->allowed($capability)),
        ));
    }
}
