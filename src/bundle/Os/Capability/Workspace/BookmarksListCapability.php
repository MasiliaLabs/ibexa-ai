<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Os\Capability\Workspace;

use Masilia\Bundle\IbexaAi\Os\Capability\RepositoryCapability;
use Masilia\IbexaAi\Os\CapabilityResult;
use Masilia\IbexaAi\Os\Risk;

final class BookmarksListCapability extends RepositoryCapability
{
    public function name(): string
    {
        return 'workspace.bookmarks.list';
    }

    public function summary(): string
    {
        return 'List the bookmarks of the user.';
    }

    public function description(): string
    {
        return 'The bookmarks are the user’s own shortcuts to locations. Returns location_id, content_id and name.';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'required' => [], 'properties' => ['limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100]]];
    }

    public function risk(): Risk
    {
        return Risk::READ;
    }

    public function requires(): array
    {
        return [];
    }

    public function examples(): array
    {
        return [['description' => 'My bookmarks', 'arguments' => []]];
    }

    public function execute(array $arguments): CapabilityResult
    {
        $list = $this->repository->getBookmarkService()->loadBookmarks(0, (int) ($arguments['limit'] ?? 25));
        $rows = [];
        foreach ($list->items as $location) {
            $rows[] = ['location_id' => $location->id, 'content_id' => $location->contentInfo->id, 'name' => $location->contentInfo->name];
        }

        return CapabilityResult::ok(sprintf('%d bookmark(s)', $list->totalCount), ['bookmarks' => $rows, 'total' => $list->totalCount]);
    }
}
