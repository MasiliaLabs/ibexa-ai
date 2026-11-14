<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Content\Trash;

use Ibexa\Contracts\Core\Repository\Repository;
use Ibexa\Contracts\Core\Repository\Values\Content\Relation;
use Masilia\Bundle\IbexaAi\Agent\Tool\ContentUpdater;
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
     * @return array{content_id: int, location_id: int, trashed: true, unlinked_from: list<string>} The unlinked list holds "content_id:name"
     */
    public function trash(int $contentId): array
    {
        $content = $this->repository->getContentService()->loadContent($contentId);
        $locationId = $content->getContentInfo()->getMainLocationId();
        $location = $this->repository->getLocationService()->loadLocation($locationId);

        $unlinked = $this->unlinkReverseRelations($contentId);
        $this->repository->getTrashService()->trash($location);

        return ['content_id' => $contentId, 'location_id' => $locationId, 'trashed' => true, 'unlinked_from' => $unlinked];
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
