<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Os\Capability\Trash;

use Ibexa\Contracts\Core\Repository\Repository;
use Ibexa\Contracts\Core\Repository\Values\Content\Query;
use Masilia\Bundle\IbexaAi\Os\Capability\BaseCapability;
use Masilia\IbexaAi\Os\CapabilityResult;
use Masilia\IbexaAi\Os\Risk;

final class ListCapability extends BaseCapability
{
    public function __construct(private readonly Repository $repository)
    {
    }

    public function name(): string
    {
        return 'trash.list';
    }

    public function summary(): string
    {
        return 'List what is in the trash.';
    }

    public function description(): string
    {
        return 'Lists the contents in the trash, with the location they had, so that they can be restored. total is the exact count.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'description' => 'Most items (default 20)'],
                'offset' => ['type' => 'integer', 'minimum' => 0],
            ],
        ];
    }

    public function risk(): Risk
    {
        return Risk::READ;
    }

    public function requires(): array
    {
        return [['content', 'restore']];
    }

    public function execute(array $arguments): CapabilityResult
    {
        $result = $this->repository->getTrashService()->findTrashItems(new Query([
            'limit' => (int) ($arguments['limit'] ?? 20),
            'offset' => (int) ($arguments['offset'] ?? 0),
        ]));

        $items = [];
        foreach ($result->items as $item) {
            $info = $item->getContentInfo();
            $items[] = ['location_id' => $item->getId(), 'content_id' => $info->getId(), 'name' => $info->getName(), 'path' => $item->pathString];
        }

        return CapabilityResult::ok(sprintf('%d items in the trash (%d shown)', $result->totalCount, count($items)), ['total' => $result->totalCount, 'items' => $items]);
    }
}
