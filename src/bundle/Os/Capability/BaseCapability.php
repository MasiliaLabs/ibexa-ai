<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Os\Capability;

use Masilia\Bundle\IbexaAi\Agent\Tool\ToolResult;
use Masilia\Contracts\IbexaAi\Os\AbstractCapability;
use Masilia\Contracts\IbexaAi\Os\CapabilityResult;

/**
 * The capabilities of this package. Another package extends {@see AbstractCapability}: this class only adds what the
 * capabilities that still wrap an agent tool need, and goes with those tools (1.0).
 */
abstract class BaseCapability extends AbstractCapability
{
    /** A capability that does what an existing tool does answers as the tool does. */
    protected static function fromTool(ToolResult $result): CapabilityResult
    {
        return $result->success ? CapabilityResult::ok($result->message, $result->data) : CapabilityResult::error($result->message);
    }
}
