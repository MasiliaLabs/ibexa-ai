<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Os\Capability;

use Masilia\Bundle\IbexaAi\Agent\Tool\ToolResult;
use Masilia\IbexaAi\Os\Capability;
use Masilia\IbexaAi\Os\CapabilityResult;
use Masilia\IbexaAi\Os\Preview;

/**
 * Defaults for a capability: no extra policy, no example, and a preview that only names the action.
 */
abstract class BaseCapability implements Capability
{
    public function requires(): array
    {
        return [];
    }

    public function examples(): array
    {
        return [];
    }

    public function preview(array $arguments): Preview
    {
        return new Preview($this->summary());
    }

    /** A capability that does what an existing tool does answers as the tool does. */
    protected static function fromTool(ToolResult $result): CapabilityResult
    {
        return $result->success ? CapabilityResult::ok($result->message, $result->data) : CapabilityResult::error($result->message);
    }
}
