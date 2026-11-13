<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Os\Capability\Structure;

use Ibexa\Contracts\Core\Repository\ContentTypeService;
use Ibexa\Contracts\Core\Repository\Exceptions\BadStateException;
use Ibexa\Contracts\Core\Repository\Values\ContentType\ContentType;
use Ibexa\Contracts\Core\Repository\Values\ContentType\ContentTypeDraft;
use Ibexa\Contracts\Core\Repository\Values\ContentType\FieldDefinitionCreateStruct;
use Masilia\Bundle\IbexaAi\Os\Capability\RepositoryCapability;
use Throwable;

/**
 * What the capabilities on content types share. A change to an existing type goes through a draft of the type that
 * is published at the end, all in one repository transaction: a failure leaves no draft behind and the type as it was.
 */
abstract class TypeCapability extends RepositoryCapability
{
    public const FIELD_ID = '/^[a-z][a-z0-9_]*$/';

    protected function types(): ContentTypeService
    {
        return $this->repository->getContentTypeService();
    }

    /** A sentence when the type does not exist, null when it does. */
    protected function missingType(string $identifier): ?string
    {
        try {
            $this->types()->loadContentTypeByIdentifier($identifier);

            return null;
        } catch (Throwable) {
            return sprintf('There is no content type "%s": see structure.type.list.', $identifier);
        }
    }

    protected function missingGroup(string $identifier): ?string
    {
        try {
            $this->types()->loadContentTypeGroupByIdentifier($identifier);

            return null;
        } catch (Throwable) {
            return sprintf('There is no group of content types "%s": see structure.group.list.', $identifier);
        }
    }

    /**
     * What is wrong with a field given to create or add, without writing anything.
     *
     * @param array<string, mixed> $field
     * @param list<string>         $taken Identifiers already used in the type
     */
    protected function fieldProblem(array $field, array $taken = []): ?string
    {
        $identifier = (string) ($field['identifier'] ?? '');
        if (preg_match(self::FIELD_ID, $identifier) !== 1) {
            return sprintf('The field identifier "%s" must be lower case letters, digits and _, starting with a letter.', $identifier);
        }
        if (in_array($identifier, $taken, true)) {
            return sprintf('The type already has a field "%s".', $identifier);
        }
        if (!$this->repository->getFieldTypeService()->hasFieldType((string) ($field['field_type'] ?? ''))) {
            return sprintf('There is no field type "%s": see structure.field_type.list.', (string) ($field['field_type'] ?? ''));
        }

        return null;
    }

    /**
     * @param array<string, mixed> $field
     */
    protected function fieldStruct(array $field): FieldDefinitionCreateStruct
    {
        $lang = $this->defaultLanguage();
        $struct = $this->types()->newFieldDefinitionCreateStruct((string) $field['identifier'], (string) $field['field_type']);
        $struct->names = [$lang => (string) ($field['name'] ?? $field['identifier'])];
        $struct->isRequired = (bool) ($field['required'] ?? false);
        $struct->isTranslatable = (bool) ($field['translatable'] ?? true);
        $struct->isSearchable = (bool) ($field['searchable'] ?? true);
        if (isset($field['settings']) && is_array($field['settings'])) {
            $struct->fieldSettings = $field['settings'];
        }
        if (isset($field['group']) && is_string($field['group'])) {
            $struct->fieldGroup = $field['group'];
        }

        return $struct;
    }

    /**
     * Changes a type through a draft and publishes it, or changes nothing.
     *
     * @param callable(ContentTypeDraft, ContentType): void $change
     */
    protected function edit(string $identifier, callable $change): ContentType
    {
        $types = $this->types();
        $this->repository->beginTransaction();
        try {
            $type = $types->loadContentTypeByIdentifier($identifier);
            try {
                $draft = $types->createContentTypeDraft($type);
            } catch (BadStateException) {
                $draft = $types->loadContentTypeDraft($type->id);
            }
            $change($draft, $type);
            $types->publishContentTypeDraft($draft);
            $this->repository->commit();
        } catch (Throwable $e) {
            $this->repository->rollback();
            throw $e;
        }

        return $types->loadContentTypeByIdentifier($identifier);
    }

    /**
     * @return list<string> A note for the approval card when contents of the type exist
     */
    protected function usedNote(string $identifier): array
    {
        try {
            $used = $this->types()->isContentTypeUsed($this->types()->loadContentTypeByIdentifier($identifier));
        } catch (Throwable) {
            return [];
        }

        return $used ? ['Contents of this type exist: they are affected.'] : [];
    }

    /**
     * @return array<string, mixed>
     */
    protected static function describeField(\Ibexa\Contracts\Core\Repository\Values\ContentType\FieldDefinition $field): array
    {
        return [
            'identifier' => $field->identifier,
            'field_type' => $field->fieldTypeIdentifier,
            'name' => $field->getName(),
            'required' => $field->isRequired,
            'translatable' => $field->isTranslatable,
            'searchable' => $field->isSearchable,
            'position' => $field->position,
            'settings' => $field->getFieldSettings(),
        ];
    }
}
