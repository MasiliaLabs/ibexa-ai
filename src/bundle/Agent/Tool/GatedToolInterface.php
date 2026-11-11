<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Agent\Tool;

/**
 * A tool whose call must be approved by the user before it runs.
 *
 * Returns the reason shown to the approver, or null when the call should not be gated
 * (for example when its arguments are invalid, so the tool reports the error to the model instead).
 */
interface GatedToolInterface
{
    /**
     * @param array<string, mixed> $arguments
     */
    public function approvalReason(array $arguments): ?string;
}
