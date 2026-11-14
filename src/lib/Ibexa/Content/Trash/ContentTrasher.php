<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Ibexa\Content\Trash;

use Ibexa\Contracts\Core\Repository\Repository;
use Ibexa\AdminUi\Specification\Content\ContentHaveAssetRelation;
use Ibexa\AdminUi\Specification\Content\ContentHaveUniqueRelation;
use Ibexa\Contracts\Core\Repository\Values\Content\ContentInfo;
use Ibexa\Contracts\Core\Repository\Values\Content\RelationType;
use Ibexa\Contracts\Core\Repository\Values\Content\VersionInfo;

/**
 * Moves a content to the trash the way the admin's trash form does: its main location is trashed (what is under it goes with it,
 * and the image assets only it uses when asked). Nothing else changes, nothing is deleted for good; trash.restore brings it back.
 * The contents that point at it are only counted (see warnings()): unlinking them is the explicit relations.unlink.
 */
readonly class ContentTrasher
{
    public function __construct(
        private Repository $repository,
    ) {
    }

    /**
     * @return array{content_id: int, location_id: int, trashed: true, trashed_assets: list<int>}
     */
    public function trash(int $contentId, bool $withAssets = false): array
    {
        $content = $this->repository->getContentService()->loadContent($contentId);
        // What the admin offers on its trash form: the image assets only this content uses go with it. Read before it is trashed.
        $assets = $withAssets ? $this->uniqueAssets($contentId) : [];
        $locationId = $content->getContentInfo()->getMainLocationId();
        $location = $this->repository->getLocationService()->loadLocation($locationId);

        $this->repository->getTrashService()->trash($location);
        $trashedAssets = [];
        foreach ($assets as $asset) {
            $assetLocationId = $asset->getMainLocationId();
            if ($assetLocationId !== null) {
                $this->repository->getTrashService()->trash($this->repository->getLocationService()->loadLocation($assetLocationId));
                $trashedAssets[] = $asset->getId();
            }
        }

        return ['content_id' => $contentId, 'location_id' => $locationId, 'trashed' => true, 'trashed_assets' => $trashedAssets];
    }

    /**
     * What the admin warns about on its trash form: the items directly below that go with it, the contents that point at it, and its
     * drafts. All three are only counted.
     *
     * @return array{children: int, reverse_relations: int, drafts: int}
     */
    public function warnings(int $contentId): array
    {
        $contents = $this->repository->getContentService();
        $info = $contents->loadContentInfo($contentId);
        $mainLocationId = $info->getMainLocationId();

        return [
            'children' => $mainLocationId !== null ? $this->repository->getLocationService()->getLocationChildCount($this->repository->getLocationService()->loadLocation($mainLocationId)) : 0,
            'reverse_relations' => $contents->countReverseRelations($info),
            'drafts' => count($contents->loadVersions($info, VersionInfo::STATUS_DRAFT)),
        ];
    }

    /**
     * The image assets that only this content uses: the ones the admin offers to trash with it. None when the content has no
     * asset, or when one of them is used elsewhere.
     *
     * @return list<ContentInfo>
     */
    public function uniqueAssets(int $contentId): array
    {
        $service = $this->repository->getContentService();
        $content = $service->loadContent($contentId);
        if (!(new ContentHaveAssetRelation($service))->and(new ContentHaveUniqueRelation($service))->isSatisfiedBy($content)) {
            return [];
        }
        $assets = [];
        foreach ($service->loadRelationList($content->getVersionInfo(), 0, 50, RelationType::ASSET)->items as $item) {
            if ($item->hasRelation()) {
                $assets[] = $item->getRelation()->getDestinationContentInfo();
            }
        }

        return $assets;
    }
}
