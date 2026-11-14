<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Os\Capability\Trash;

use Masilia\Bundle\IbexaAi\Ibexa\Content\Trash\TrashRestorer;
use Masilia\Contracts\IbexaAi\Os\AbstractCapability;
use Masilia\Contracts\IbexaAi\Os\CapabilityResult;
use Masilia\Contracts\IbexaAi\Os\Preview;
use Masilia\Contracts\IbexaAi\Os\Risk;

/** Puts trashed contents back where they were. Writes through the trash service, with the rights of the current user. */
final class RestoreCapability extends AbstractCapability
{
    public function __construct(private readonly TrashRestorer $restorer)
    {
    }

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
        return 'Restores the given contents from the trash (ids as trash.list gives them) to where they were, or below parent_location_id when given (the admin offers it when the original parent is gone). Ids that are not in the trash are ignored.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['content_ids'],
            'properties' => [
                'content_ids' => ['type' => 'array', 'items' => ['type' => 'integer', 'minimum' => 1], 'description' => 'Content ids, as trash.list gives them'],
                'parent_location_id' => ['type' => 'integer', 'minimum' => 1, 'description' => 'Restore below this location instead of the original one'],
            ],
        ];
    }

    public function risk(): Risk
    {
        return Risk::WRITE;
    }

    public function requires(): array
    {
        return [['content', 'restore']];
    }

    public function examples(): array
    {
        return [['description' => 'Restore two contents', 'arguments' => ['content_ids' => [1353, 1354]]]];
    }

    public function validate(array $arguments): ?string
    {
        return $arguments['content_ids'] === [] ? 'trash.restore requires at least one content id (content_ids).' : $this->parentProblem($arguments);
    }

    public function preview(array $arguments): Preview
    {
        $rows = [['Contents', implode(', ', array_map('strval', $arguments['content_ids']))]];
        if (isset($arguments['parent_location_id'])) {
            $rows[] = ['Below', 'location ' . (int) $arguments['parent_location_id']];
        }

        return new Preview('Restore from the trash', $rows);
    }

    public function execute(array $arguments): CapabilityResult
    {
        $restored = $this->restorer->restore(array_values(array_map('intval', $arguments['content_ids'])), isset($arguments['parent_location_id']) ? (int) $arguments['parent_location_id'] : null);

        return CapabilityResult::ok(sprintf('Restored %d items', count($restored)), ['restored' => $restored, 'count' => count($restored)]);
    }

    /**
     * @param array<string, mixed> $arguments
     */
    private function parentProblem(array $arguments): ?string
    {
        if (!isset($arguments['parent_location_id'])) {
            return null;
        }
        try {
            $parent = $this->restorer->parentLocation((int) $arguments['parent_location_id']);
        } catch (\Throwable) {
            return sprintf('The location %d does not exist.', (int) $arguments['parent_location_id']);
        }

        return $parent->getContentInfo()->getContentType()->isContainer() ? null : 'Contents can only be restored below a container.';
    }
}
