<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Os\Capability\Location;

use Masilia\Contracts\IbexaAi\Os\AbstractCapability;
use Ibexa\Contracts\Core\Repository\Repository;
use Masilia\IbexaAi\Ibexa\Rules\LocationRules;
use Ibexa\Contracts\Core\Repository\Values\Content\Location;
use Throwable;

/**
 * What the location capabilities share: loading a location, and naming one for the person who approves.
 */
abstract class LocationCapability extends AbstractCapability
{
    public function __construct(protected readonly Repository $repository, protected readonly LocationRules $rules)
    {
    }

    /** The first of these locations that does not exist, as a sentence; null when all exist. */
    protected function missing(int ...$ids): ?string
    {
        foreach ($ids as $id) {
            try {
                $this->repository->getLocationService()->loadLocation($id);
            } catch (Throwable) {
                return sprintf('The location %d does not exist.', $id);
            }
        }

        return null;
    }

    protected function load(int $id): Location
    {
        return $this->repository->getLocationService()->loadLocation($id);
    }

    /** "Name (location 12)", or just the id when the location cannot be read. */
    protected function label(int $id): string
    {
        try {
            return sprintf('%s (location %d)', $this->load($id)->getContent()->getName(), $id);
        } catch (Throwable) {
            return sprintf('location %d', $id);
        }
    }

    /** "Name (content 5)", or just the id when the content cannot be read. */
    protected function contentName(int $id): string
    {
        try {
            return sprintf('%s (content %d)', $this->repository->getContentService()->loadContentInfo($id)->name, $id);
        } catch (Throwable) {
            return sprintf('content %d', $id);
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected static function row(Location $location): array
    {
        return [
            'location_id' => $location->id,
            'content_id' => $location->contentInfo->id,
            'parent_location_id' => $location->parentLocationId,
            'name' => $location->contentInfo->name,
            'is_main' => $location->contentInfo->mainLocationId === $location->id,
            'hidden' => $location->hidden,
            'invisible' => $location->invisible,
            'priority' => $location->priority,
            'path' => $location->pathString,
        ];
    }
}
