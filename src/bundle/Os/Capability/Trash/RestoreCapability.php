<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Os\Capability\Trash;

use Masilia\Bundle\IbexaAi\Agent\Worker\Plan;
use Masilia\Bundle\IbexaAi\Os\Capability\PlanCapability;

final class RestoreCapability extends PlanCapability
{
    public function name(): string
    {
        return 'trash.restore';
    }

    public function summary(): string
    {
        return 'Restore contents from the trash to where they were.';
    }

    public function description(): string
    {
        return 'Brings contents back from the trash, to their former location. Find their content ids with trash.list.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['content_ids'],
            'properties' => ['content_ids' => ['type' => 'array', 'items' => ['type' => 'integer', 'minimum' => 1], 'description' => 'Content ids, as trash.list gives them']],
        ];
    }

    public function requires(): array
    {
        return [['content', 'restore']];
    }

    public function examples(): array
    {
        return [['description' => 'Restore two contents', 'arguments' => ['content_ids' => [1353, 1354]]]];
    }

    protected function plan(array $arguments): array
    {
        return ['intent' => Plan::INTENT_RESTORE_CONTENT, 'fields' => ['content_ids' => array_values((array) ($arguments['content_ids'] ?? []))]];
    }
}
