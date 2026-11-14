<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Agent\Tool;

use Ibexa\Contracts\Core\Repository\Exceptions\BadStateException;
use Ibexa\Contracts\Core\Repository\Exceptions\ContentFieldValidationException;
use Ibexa\Contracts\Core\Repository\Exceptions\ContentValidationException;
use Ibexa\Contracts\Core\Repository\Exceptions\InvalidArgumentException;
use Ibexa\Contracts\Core\Repository\Exceptions\NotFoundException;
use Ibexa\Contracts\Core\Repository\Exceptions\UnauthorizedException;
use Ibexa\Contracts\Core\Repository\Repository;
use Ibexa\Contracts\Core\Repository\Values\Content\ContentCreateStruct;
use Ibexa\Contracts\Core\Repository\Values\ContentType\ContentType;
use RuntimeException;

/**
 * Create + publish content items, with field-value transformation.
 *
 * Extracted from the previous {@see ContentPublishHelper} monolith to keep
 * creation responsibilities separate from update responsibilities.
 */
final readonly class ContentCreator
{
    public function __construct(
        private Repository $repository,
        private FieldValueTransformerRegistry $transformerRegistry,
    ) {
    }

    public function createAndPublish(
        string $contentTypeIdentifier,
        array $parentLocationIds,
        array $attributes,
        string $languageCode,
        ?string $remoteId = null,
        ?string $locationRemoteId = null,
        ?string $skipFieldId = null,
    ): array {
        $contentService = $this->repository->getContentService();
        $locationService = $this->repository->getLocationService();
        $contentTypeService = $this->repository->getContentTypeService();

        try {
            $contentType = $contentTypeService->loadContentTypeByIdentifier($contentTypeIdentifier);
        } catch (NotFoundException $e) {
            $contentType = null;
        }
        if (!$contentType) {
            return [];
        }
        $createStruct = $contentService->newContentCreateStruct($contentType, $languageCode);
        $createStruct->remoteId = $remoteId;

        $this->applyFields($createStruct, $contentType, $attributes, $languageCode, $skipFieldId);

        $locStructs = [];
        foreach ($parentLocationIds as $parentId) {
            $locStruct = $locationService->newLocationCreateStruct($parentId);
            if ($locationRemoteId !== null) {
                $locStruct->remoteId = $locationRemoteId . '-' . $parentId;
            }
            $locStructs[] = $locStruct;
        }

        try {
            $draft = $contentService->createContent($createStruct, $locStructs);
            $published = $contentService->publishVersion($draft->getVersionInfo());
        } catch (BadStateException|ContentFieldValidationException|ContentValidationException|InvalidArgumentException|UnauthorizedException $e) {
            throw new RuntimeException(
                sprintf('Failed to create content of type "%s": %s', $contentTypeIdentifier, $e->getMessage()),
                previous: $e,
            );
        }

        return [
            'content' => $published,
            'location' => $published->getContentInfo()->getMainLocation(),
        ];
    }

    /**
     * Set fields on a create struct, transforming values via the registry.
     *
     */
    public function applyFields(
        ContentCreateStruct $createStruct,
        ContentType $contentType,
        array $fields,
        string $languageCode,
        ?string $skipFieldId = null,
    ): void {
        foreach ($contentType->getFieldDefinitions() as $fieldDef) {
            if ($fieldDef->getIdentifier() === $skipFieldId) {
                continue;
            }
            if (!array_key_exists($fieldDef->getIdentifier(), $fields)) {
                continue;
            }

            $transformedValue = $this->transformerRegistry->transform(
                $fieldDef,
                $fields[$fieldDef->getIdentifier()],
            );
            $createStruct->setField($fieldDef->getIdentifier(), $transformedValue, $languageCode);
        }
    }
}
