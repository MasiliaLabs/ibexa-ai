<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Os\Capability\Content;

use Masilia\Bundle\IbexaAi\Agent\Worker\Plan;
use Masilia\Bundle\IbexaAi\Os\Capability\PlanCapability;

final class TrashCapability extends PlanCapability
{
    public function name(): string
    {
        return 'content.trash';
    }

    public function summary(): string
    {
        return 'Move a content to the trash (it can be restored).';
    }

    public function description(): string
    {
        return 'Moves the content, and what is under its location, to the trash. Nothing is deleted for good: trash.restore brings it back.';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'required' => ['content_id'], 'properties' => ['content_id' => ['type' => 'integer', 'minimum' => 1]]];
    }

    public function requires(): array
    {
        return [['content', 'remove']];
    }

    public function examples(): array
    {
        return [['description' => 'Trash a page', 'arguments' => ['content_id' => 1353]]];
    }

    protected function plan(array $arguments): array
    {
        return ['intent' => Plan::INTENT_TRASH_CONTENT, 'content_id' => $arguments['content_id'] ?? null];
    }
}
