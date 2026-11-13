<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Os\Tool;

use Masilia\Bundle\IbexaAi\Agent\Tool\GatedToolInterface;
use Masilia\Bundle\IbexaAi\Agent\Tool\ToolInterface;
use Masilia\Bundle\IbexaAi\Agent\Tool\ToolResult;
use Masilia\Bundle\IbexaAi\Os\Execution\CapabilityRunner;

/**
 * Runs a capability with the rights of the current user. What changes something is approved first: whether it is
 * approved is decided by the risk of the capability, never by the model.
 */
final readonly class RunCapabilityTool implements ToolInterface, GatedToolInterface
{
    public function __construct(private CapabilityRunner $runner)
    {
    }

    public function getName(): string
    {
        return 'run_capability';
    }

    public function getDescription(): string
    {
        return 'Run one capability found with find_capabilities and read with describe_capability. Whatever changes something is shown '
            . 'to the user for approval by the system: never ask for confirmation in your reply. A refused call comes back with the reason. '
            . 'For several changes that belong together, use propose_changes: one approval for all of them.';
    }

    public function getParameters(): array
    {
        return [
            'type' => 'object',
            'required' => ['name'],
            'properties' => [
                'name' => ['type' => 'string', 'description' => 'The capability, e.g. "content.update"'],
                'arguments' => ['type' => 'object', 'description' => 'Its arguments, as described by describe_capability'],
            ],
        ];
    }

    /**
     * The reason shown to the user, for a call that changes something and can run; null when nothing is to be approved
     * (a read, or a call that cannot run and will say why).
     */
    public function approvalReason(array $arguments): ?string
    {
        [$capability, $args, $error] = $this->runner->prepare((string) ($arguments['name'] ?? ''), $arguments['arguments'] ?? []);
        if ($capability === null || $error !== null || !$capability->risk()->needsApproval()) {
            return null;
        }

        return $this->runner->preview($capability, $args)->toApprovalText($capability->risk());
    }

    public function execute(array $params): ToolResult
    {
        [$capability, $args, $error] = $this->runner->prepare((string) ($params['name'] ?? ''), $params['arguments'] ?? []);
        if ($capability === null || $error !== null) {
            return ToolResult::error($error ?? 'Unknown capability.');
        }

        $result = $this->runner->run($capability, $args);

        return $result->success ? ToolResult::ok($result->message, $result->data) : ToolResult::error($result->message);
    }
}
