<?php

declare(strict_types=1);

namespace Masilia\Contracts\IbexaAi\Os;

/**
 * Defaults for a capability: no policy beyond the one of its risk, no example, nothing to check before it runs, and a
 * preview that only names the action. A capability says what it is (name, summary, description, schema, risk) and does it.
 */
abstract class AbstractCapability implements Capability
{
    public function requires(): array
    {
        return [];
    }

    public function examples(): array
    {
        return [];
    }

    public function validate(array $arguments): ?string
    {
        return null;
    }

    public function preview(array $arguments): Preview
    {
        return new Preview($this->summary());
    }
}
