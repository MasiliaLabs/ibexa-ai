<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Os\Tool;

use Masilia\Bundle\IbexaAi\Agent\Tool\ToolInterface;
use Masilia\Bundle\IbexaAi\Agent\Tool\ToolResult;
use Masilia\Bundle\IbexaAi\Os\Access\CapabilityAccess;
use Masilia\Contracts\IbexaAi\Os\Capability;
use Masilia\IbexaAi\Os\CapabilityRegistry;

/**
 * How the model learns what it can do: the capabilities the current user may use, by words or by domain.
 */
final readonly class FindCapabilitiesTool implements ToolInterface
{
    public function __construct(
        private CapabilityRegistry $registry,
        private CapabilityAccess $access,
    ) {
    }

    public function getName(): string
    {
        return 'find_capabilities';
    }

    public function getDescription(): string
    {
        return 'Find what you can do in the admin. Give words (e.g. "move a page", "translation", "trash") and/or a domain; '
            . 'without arguments it lists the domains and what each holds. Only capabilities the current user may use are listed. '
            . 'Then read one with describe_capability and run it with run_capability.';
    }

    public function getParameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => ['type' => 'string', 'description' => 'Words about what you want to do'],
                'domain' => ['type' => 'string', 'description' => 'A domain, e.g. "content" or "trash"'],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 25, 'description' => 'Most results (default 12)'],
            ],
        ];
    }

    public function execute(array $params): ToolResult
    {
        $allowed = fn (Capability $capability): bool => $this->access->allowed($capability);
        $query = is_string($params['query'] ?? null) ? $params['query'] : '';
        $domain = is_string($params['domain'] ?? null) && $params['domain'] !== '' ? $params['domain'] : null;

        $found = $this->registry->search($query, $domain, $allowed, (int) ($params['limit'] ?? 12));
        $domains = $this->registry->domains($allowed);

        return ToolResult::ok(
            $found === [] ? 'No capability matches. Try other words, or list the domains by calling this without arguments.' : sprintf('%d capabilities', count($found)),
            [
                'domains' => $domains,
                'capabilities' => array_map(static fn (Capability $capability): array => [
                    'name' => $capability->name(),
                    'risk' => $capability->risk()->value,
                    'summary' => $capability->summary(),
                ], $found),
            ],
        );
    }
}
