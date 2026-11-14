<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Ibexa\Content\Write;

use Ibexa\Contracts\Core\Repository\Exceptions\BadStateException;
use Ibexa\Contracts\Core\Repository\Exceptions\ContentFieldValidationException;
use Ibexa\Contracts\Core\Repository\Exceptions\ContentValidationException;
use Ibexa\Contracts\Core\Repository\Exceptions\InvalidArgumentException as IbexaInvalidArgumentException;
use Ibexa\Contracts\Core\Repository\Exceptions\NotFoundException;
use Ibexa\Contracts\Core\Repository\Exceptions\UnauthorizedException;
use Ibexa\Contracts\Core\Repository\Repository;
use Ibexa\Contracts\Core\Repository\Values\Content\Content;
use Ibexa\Contracts\Core\Repository\Values\Content\ContentCreateStruct;
use Ibexa\Contracts\Core\Repository\Values\Content\ContentUpdateStruct;
use Ibexa\Contracts\Core\Repository\Values\ContentType\ContentType;
use Masilia\IbexaAi\Ibexa\Field\Input\FieldInputs;
use Masilia\Contracts\IbexaAi\Content\ContentWriter;
use Masilia\Contracts\IbexaAi\Content\PublishedContent;
use Throwable;

/**
 * Creates and updates contents the way the editing form of the admin does: a draft is made, filled and published, with the
 * rights of the current user. Each value is turned into the one of its field type first (see FieldInputs). A write is one
 * transaction: when Ibexa refuses a value, no draft is left behind.
 */
final readonly class RepositoryContentWriter implements ContentWriter
{
    public function __construct(
        private Repository $repository,
        private FieldInputs $inputs,
    ) {
    }

    public function create(string $contentType, array $parentLocationIds, array $fields, string $language, bool $publish = true): PublishedContent
    {
        try {
            $type = $this->repository->getContentTypeService()->loadContentTypeByIdentifier($contentType);
        } catch (NotFoundException) {
            throw new \InvalidArgumentException(sprintf('There is no content type "%s".', $contentType));
        }

        $contents = $this->repository->getContentService();
        $locations = $this->repository->getLocationService();
        $struct = $contents->newContentCreateStruct($type, $language);
        $this->fill($struct, $type, $fields, $language);
        $parents = array_map(static fn (int $id) => $locations->newLocationCreateStruct($id), $parentLocationIds);

        $this->repository->beginTransaction();
        try {
            $draft = $contents->createContent($struct, $parents);
            $published = $publish ? $contents->publishVersion($draft->getVersionInfo()) : $draft;
            $this->repository->commit();
        } catch (BadStateException|ContentFieldValidationException|ContentValidationException|IbexaInvalidArgumentException|UnauthorizedException $e) {
            $this->repository->rollback();

            throw new \RuntimeException(sprintf('Failed to create content of type "%s": %s', $contentType, $e->getMessage()), previous: $e);
        } catch (Throwable $e) {
            $this->repository->rollback();

            throw $e;
        }

        return new PublishedContent($published, $publish ? $published->getContentInfo()->getMainLocation() : null);
    }

    public function update(int $contentId, array $fields, string $language, bool $publish = true, ?string $baseLanguage = null): Content
    {
        $contents = $this->repository->getContentService();
        $content = $contents->loadContent($contentId);
        $type = $content->getContentType();

        $struct = $contents->newContentUpdateStruct();
        $struct->initialLanguageCode = $language;
        $set = $this->fill($struct, $type, $fields, $language);
        if ($baseLanguage !== null && $baseLanguage !== $language) {
            $set += $this->startFrom($struct, $contents->loadContent($contentId, [$baseLanguage]), $fields, $language, $baseLanguage);
        }
        if ($set === 0) {
            throw new \InvalidArgumentException(sprintf(
                'No fields matched the provided attributes for content %d. Available fields: %s',
                $contentId,
                implode(', ', array_map(static fn ($field): string => $field->getIdentifier(), iterator_to_array($type->getFieldDefinitions()))),
            ));
        }

        $this->repository->beginTransaction();
        try {
            $draft = $contents->createContentDraft($content->getContentInfo());
            $contents->updateContent($draft->getVersionInfo(), $struct);
            $published = $publish ? $contents->publishVersion($draft->getVersionInfo()) : $contents->loadContent($contentId, null, $draft->getVersionInfo()->getVersionNo());
            $this->repository->commit();
        } catch (Throwable $e) {
            $this->repository->rollback();

            throw $e;
        }

        return $published;
    }

    /**
     * A new translation starts from the translatable fields of another language, as the translation form of the admin does: what
     * the model gave replaces them, and what it left out keeps the base text for the user to review.
     *
     * @param array<string, mixed> $given
     *
     * @return int How many fields started from the base language
     */
    private function startFrom(ContentUpdateStruct $struct, Content $base, array $given, string $language, string $baseLanguage): int
    {
        $started = 0;
        foreach ($base->getContentType()->getFieldDefinitions() as $definition) {
            if ($definition->isTranslatable() && !array_key_exists($definition->getIdentifier(), $given)) {
                $struct->setField($definition->getIdentifier(), $base->getFieldValue($definition->getIdentifier(), $baseLanguage), $language);
                ++$started;
            }
        }

        return $started;
    }

    /**
     * Sets, on a create or update struct, the fields of the content type that were given.
     *
     * @param array<string, mixed> $fields
     *
     * @return int How many fields were set
     */
    private function fill(ContentCreateStruct|ContentUpdateStruct $struct, ContentType $type, array $fields, string $language): int
    {
        $set = 0;
        foreach ($type->getFieldDefinitions() as $definition) {
            if (!array_key_exists($definition->getIdentifier(), $fields)) {
                continue;
            }
            $struct->setField($definition->getIdentifier(), $this->inputs->transform($definition, $fields[$definition->getIdentifier()]), $language);
            ++$set;
        }

        return $set;
    }
}
