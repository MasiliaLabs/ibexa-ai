<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Os\Capability\Trash;

use Masilia\IbexaAi\Os\Capability\RepositoryCapability;
use Masilia\Contracts\IbexaAi\Os\CapabilityResult;
use Masilia\Contracts\IbexaAi\Os\Preview;
use Masilia\Contracts\IbexaAi\Os\Risk;

final class DeleteCapability extends RepositoryCapability
{
    public function name(): string
    {
        return 'trash.delete';
    }

    public function summary(): string
    {
        return 'Delete one item of the trash for good.';
    }

    public function description(): string
    {
        return 'Deletes the trashed content (and its subtree items) permanently. Find content ids with trash.list. To keep it, use trash.restore.';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'required' => ['content_id'], 'properties' => ['content_id' => ['type' => 'integer', 'minimum' => 1]]];
    }

    public function risk(): Risk
    {
        return Risk::DESTRUCTIVE;
    }

    public function requires(): array
    {
        return [['content', 'cleantrash']];
    }

    public function examples(): array
    {
        return [['description' => 'Delete a trashed page for good', 'arguments' => ['content_id' => 1353]]];
    }

    public function validate(array $arguments): ?string
    {
        return $this->item((int) $arguments['content_id']) === null ? sprintf('The content %d is not in the trash: see trash.list.', (int) $arguments['content_id']) : null;
    }

    public function preview(array $arguments): Preview
    {
        return new Preview('Delete from the trash', [['Content', $this->itemLabel((int) $arguments['content_id'])]], ['It is deleted for good. This cannot be undone.']);
    }

    private function item(int $contentId): ?\Ibexa\Contracts\Core\Repository\Values\Content\TrashItem
    {
        $query = new \Ibexa\Contracts\Core\Repository\Values\Content\Query(['limit' => 500]);
        foreach ($this->repository->getTrashService()->findTrashItems($query)->items as $item) {
            if ($item->contentInfo->id === $contentId) {
                return $item;
            }
        }

        return null;
    }

    private function itemLabel(int $contentId): string
    {
        $item = $this->item($contentId);

        return $item !== null ? sprintf('%s (content %d)', $item->contentInfo->name, $contentId) : sprintf('content %d', $contentId);
    }

    public function execute(array $arguments): CapabilityResult
    {
        $item = $this->item((int) $arguments['content_id']);
        if ($item === null) {
            return CapabilityResult::error(sprintf('The content %d is not in the trash.', (int) $arguments['content_id']));
        }
        $this->repository->getTrashService()->deleteTrashItem($item);

        return CapabilityResult::ok(sprintf('"%s" was deleted for good', $item->contentInfo->name), []);
    }
}
