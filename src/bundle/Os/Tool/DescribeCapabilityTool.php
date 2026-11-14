<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Os\Tool;

use Masilia\Bundle\IbexaAi\Agent\Tool\ToolInterface;
use Masilia\Bundle\IbexaAi\Agent\Tool\ToolResult;
use Masilia\Bundle\IbexaAi\Os\Access\CapabilityAccess;
use Masilia\Contracts\IbexaAi\Os\Capability;
use Masilia\IbexaAi\Os\CapabilityRegistry;

/**
 * What the model reads before it runs a capability: what it does, its arguments, its risk, examples.
 */
final readonly class DescribeCapabilityTool implements ToolInterface
{
    public function __construct(
        private CapabilityRegistry $registry,
        private CapabilityAccess $access,
    ) {
    }

    public function getName(): string
    {
        return 'describe_capability';
    }

    public function getDescription(): string
    {
        return 'Read one capability before running it: what it does, the arguments it takes (JSON Schema), whether the user will be asked to approve it, and examples.';
    }

    public function getParameters(): array
    {
        return [
            'type' => 'object',
            'required' => ['name'],
            'properties' => ['name' => ['type' => 'string', 'description' => 'The capability, e.g. "content.update"']],
        ];
    }

    public function execute(array $params): ToolResult
    {
        $name = is_string($params['name'] ?? null) ? trim($params['name']) : '';
        $capability = $this->registry->get($name);
        if ($capability === null) {
            $near = array_map(static fn (Capability $c): string => $c->name(), $this->registry->search(str_replace('.', ' ', $name), null, fn (Capability $c): bool => $this->access->allowed($c), 5));

            return ToolResult::error(sprintf('There is no capability "%s".%s', $name, $near !== [] ? ' Did you mean: ' . implode(', ', $near) . '?' : ' Use find_capabilities.'));
        }
        $denial = $this->access->denial($capability);
        if ($denial !== null) {
            return ToolResult::error(sprintf('%s is not available: %s', $name, $denial));
        }

        return ToolResult::ok(sprintf('%s: %s', $capability->name(), $capability->summary()), [
            'name' => $capability->name(),
            'risk' => $capability->risk()->value,
            'user_approves_first' => $capability->risk()->needsApproval(),
            'description' => $capability->description(),
            'input_schema' => $capability->inputSchema(),
            'examples' => $capability->examples(),
        ]);
    }
}
