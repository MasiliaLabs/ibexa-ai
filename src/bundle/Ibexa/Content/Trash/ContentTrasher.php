<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Ibexa\Content\Trash;

use Ibexa\Contracts\Core\Repository\Repository;
use Ibexa\AdminUi\Specification\Content\ContentHaveAssetRelation;
use Ibexa\AdminUi\Specification\Content\ContentHaveUniqueRelation;
use Ibexa\Contracts\Core\Repository\Values\Content\ContentInfo;
use Ibexa\Contracts\Core\Repository\Values\Content\Relation;
use Ibexa\Contracts\Core\Repository\Values\Content\RelationType;
use Masilia\Bundle\IbexaAi\Ibexa\Content\Write\ContentUpdater;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Moves a content to the trash, the way the assistant always has: first it is unlinked from the relation fields of the
 * other contents that point at it (so that no field is left pointing at a trashed content), then its main location is
 * trashed. Nothing is deleted for good; {@see \Masilia\Bundle\IbexaAi\Agent\Tool\TrashRestorer} brings it back.
 */
readonly class ContentTrasher
{
    public function __construct(
        private Repository $repository,
        private ContentUpdater $contentUpdater,
        private LoggerInterface $aiLogger,
    ) {
    }

    /**
     * @return array{content_id: int, location_id: int, trashed: true, unlinked_from: list<string>, trashed_assets: list<int>} The unlinked list holds "content_id:name"
     */
    public function trash(int $contentId, bool $withAssets = false): array
    {
        $content = $this->repository->getContentService()->loadContent($contentId);
        // What the admin offers on its trash form: the image assets only this content uses go with it. Read before it is trashed.
        $assets = $withAssets ? $this->uniqueAssets($contentId) : [];
        $locationId = $content->getContentInfo()->getMainLocationId();
        $location = $this->repository->getLocationService()->loadLocation($locationId);

        $unlinked = $this->unlinkReverseRelations($contentId);
        $this->repository->getTrashService()->trash($location);
        $trashedAssets = [];
        foreach ($assets as $asset) {
            $assetLocationId = $asset->getMainLocationId();
            if ($assetLocationId !== null) {
                $this->repository->getTrashService()->trash($this->repository->getLocationService()->loadLocation($assetLocationId));
                $trashedAssets[] = $asset->getId();
            }
        }

        return ['content_id' => $contentId, 'location_id' => $locationId, 'trashed' => true, 'unlinked_from' => $unlinked, 'trashed_assets' => $trashedAssets];
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

    /**
     * Removes the trashed content from the relation fields of the contents that point at it. A content that cannot be
     * updated is logged and left as it is: the trash still happens.
     *
     * @return list<string> "content_id:name" of each content that was updated
     */
    private function unlinkReverseRelations(int $contentId): array
    {
        $contentService = $this->repository->getContentService();
        $languageCode = $this->repository->getContentLanguageService()->getDefaultLanguageCode();

        $reverseRelations = $contentService->loadReverseRelations($contentService->loadContentInfo($contentId));

        // Source content => field identifier => the trashed content, for each relation field
        $updates = [];
        foreach ($reverseRelations as $relation) {
            if ($relation->type !== Relation::FIELD) {
                continue;
            }
            $updates[$relation->getSourceContentInfo()->getId()][$relation->sourceFieldDefinitionIdentifier][] = $contentId;
        }

        $updated = [];
        foreach ($updates as $sourceContentId => $fields) {
            try {
                $sourceContent = $contentService->loadContent($sourceContentId, [$languageCode]);
                $attributes = [];
                foreach ($fields as $fieldIdentifier => $idsToRemove) {
                    $fieldValue = $sourceContent->getFieldValue($fieldIdentifier);
                    // A relation list keeps what it has but the trashed content; a single relation pointing at it is emptied.
                    if (is_object($fieldValue) && property_exists($fieldValue, 'destinationContentIds')) {
                        $attributes[$fieldIdentifier] = array_values(array_diff($fieldValue->destinationContentIds, $idsToRemove));
                    } elseif (is_object($fieldValue) && property_exists($fieldValue, 'destinationContentId') && in_array($fieldValue->destinationContentId, $idsToRemove, true)) {
                        $attributes[$fieldIdentifier] = null;
                    }
                }

                if ($attributes !== []) {
                    $this->contentUpdater->update($sourceContentId, $attributes, $languageCode);
                    $updated[] = sprintf('%d:%s', $sourceContentId, $sourceContent->getContentInfo()->getName());
                }
            } catch (Throwable $e) {
                $this->aiLogger->warning('[ContentTrasher] Failed to unlink reverse relation from content {sourceId}: {message}', [
                    'sourceId' => $sourceContentId,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        return $updated;
    }
}
