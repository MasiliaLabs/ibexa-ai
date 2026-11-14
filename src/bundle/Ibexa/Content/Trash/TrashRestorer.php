<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Ibexa\Content\Trash;

use Ibexa\Contracts\Core\Repository\Repository;
use Ibexa\Contracts\Core\Repository\Values\Content\Location;
use Ibexa\Contracts\Core\Repository\Values\Content\Query;

/**
 * Shared trash restoration logic used by
 * RestoreCapability.
 *
 * The trash cannot be searched by content id: the criterion is not implemented for the trash by the
 * search engines this project uses (it throws NotImplementedException), and a trashed content no longer
 * has a main location to load the trash item from. So the trash is read page by page and filtered here.
 */
class TrashRestorer
{
    private const PAGE_SIZE = 100;
    /** Upper bound of trash items looked at, so a huge trash cannot make one call run for long. */
    private const MAX_SCANNED = 5000;

    public function __construct(
        private readonly Repository $repository,
    ) {
    }

    /**
     * Restore trashed content items by their content IDs.
     *
     * @param int[] $contentIds
     * @param int|null $newParentLocationId Restore below this location instead of the original one (the admin's option)
     * @return int[] IDs of restored content items
     */
    public function restore(array $contentIds, ?int $newParentLocationId = null): array
    {
        $trashService = $this->repository->getTrashService();
        $newParent = $newParentLocationId !== null ? $this->parentLocation($newParentLocationId) : null;

        $restored = [];
        foreach ($this->findTrashed($contentIds) as $trashed) {
            $trashService->recover($trashed, $newParent);
            $restored[] = $trashed->getContentId();
        }

        return $restored;
    }

    public function parentLocation(int $locationId): Location
    {
        return $this->repository->getLocationService()->loadLocation($locationId);
    }

    /**
     * @param int[] $contentIds
     * @return list<\Ibexa\Contracts\Core\Repository\Values\Content\TrashItem>
     */
    private function findTrashed(array $contentIds): array
    {
        $wanted = array_fill_keys(array_map('intval', $contentIds), true);
        if ($wanted === []) {
            return [];
        }

        $trashService = $this->repository->getTrashService();
        $found = [];

        for ($offset = 0; $offset < self::MAX_SCANNED; $offset += self::PAGE_SIZE) {
            $page = $trashService->findTrashItems(new Query(['limit' => self::PAGE_SIZE, 'offset' => $offset]));

            foreach ($page->items as $item) {
                if (isset($wanted[$item->getContentId()])) {
                    $found[] = $item;
                    unset($wanted[$item->getContentId()]);
                }
            }

            // Everything wanted was found, or this was the last page.
            if ($wanted === [] || count($page->items) < self::PAGE_SIZE) {
                break;
            }
        }

        return $found;
    }
}
