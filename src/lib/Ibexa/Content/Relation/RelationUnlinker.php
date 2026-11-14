<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Ibexa\Content\Relation;

use Ibexa\Contracts\Core\Repository\Repository;
use Ibexa\Contracts\Core\Repository\Values\Content\Relation;
use Masilia\Contracts\IbexaAi\Content\ContentWriter;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Removes a content from the relation fields of the other contents that point at it, so that no field is left pointing at what
 * is about to go. The admin only warns about those contents when a content is trashed; this is the explicit step that the
 * assistant takes when the user wants them unlinked (each one gets a new version, written with the user's rights).
 */
readonly class RelationUnlinker
{
    public function __construct(
        private Repository $repository,
        private ContentWriter $writer,
        private LoggerInterface $aiLogger,
    ) {
    }

    /**
     * The contents that hold the content in a relation field, and the fields that hold it.
     *
     * @return array<int, list<string>> Source content id => identifiers of its relation fields
     */
    public function sources(int $contentId): array
    {
        $contents = $this->repository->getContentService();
        $sources = [];
        foreach ($contents->loadReverseRelations($contents->loadContentInfo($contentId)) as $relation) {
            if ($relation->type !== Relation::FIELD) {
                continue;
            }
            $field = (string) $relation->sourceFieldDefinitionIdentifier;
            $sourceId = $relation->getSourceContentInfo()->getId();
            if (!in_array($field, $sources[$sourceId] ?? [], true)) {
                $sources[$sourceId][] = $field;
            }
        }

        return $sources;
    }

    /**
     * @return array{content_id: int, unlinked_from: list<string>, failed: list<string>} Each entry is "content_id:name"
     */
    public function unlink(int $contentId): array
    {
        $contents = $this->repository->getContentService();
        $languageCode = $this->repository->getContentLanguageService()->getDefaultLanguageCode();

        $updated = [];
        $failed = [];
        foreach ($this->sources($contentId) as $sourceContentId => $fields) {
            $label = (string) $sourceContentId;
            try {
                $source = $contents->loadContent($sourceContentId, [$languageCode]);
                $label = sprintf('%d:%s', $sourceContentId, $source->getContentInfo()->getName());
                $attributes = [];
                foreach ($fields as $fieldIdentifier) {
                    $value = $source->getFieldValue($fieldIdentifier);
                    // A relation list keeps what it has but the content; a single relation pointing at it is emptied.
                    if (is_object($value) && property_exists($value, 'destinationContentIds')) {
                        $attributes[$fieldIdentifier] = array_values(array_diff($value->destinationContentIds, [$contentId]));
                    } elseif (is_object($value) && property_exists($value, 'destinationContentId') && $value->destinationContentId === $contentId) {
                        $attributes[$fieldIdentifier] = null;
                    }
                }

                if ($attributes !== []) {
                    $this->writer->update($sourceContentId, $attributes, $languageCode);
                    $updated[] = $label;
                }
            } catch (Throwable $e) {
                $this->aiLogger->warning('[RelationUnlinker] Failed to unlink content {contentId} from content {sourceId}: {message}', [
                    'contentId' => $contentId,
                    'sourceId' => $sourceContentId,
                    'message' => $e->getMessage(),
                ]);
                $failed[] = $label;
            }
        }

        return ['content_id' => $contentId, 'unlinked_from' => $updated, 'failed' => $failed];
    }
}
