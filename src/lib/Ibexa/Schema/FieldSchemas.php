<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Ibexa\Schema;

use Ibexa\Contracts\Core\Repository\Values\ContentType\ContentType;
use Ibexa\Contracts\Core\Repository\Values\ContentType\FieldDefinition;
use Masilia\IbexaAi\Ibexa\Field\FieldTypes;

/** What a model must know to fill the fields of a content type: per field its type, whether it is required, and the constraints of its settings. */
final readonly class FieldSchemas
{
    /**
     * Build a detailed schema entry for a single field definition.
     *
     * Always returns at minimum `{type, required, translatable}`. Adds `description`
     * when the field definition has a non-empty admin description, plus field-type-
     * specific extras (columns, allowedTypes, options, length/size limits, etc.).
     *
     * @return array<string, mixed>
     */
    public function forField(FieldDefinition $fieldDef): array
    {
        $info = [
            'type' => $fieldDef->getFieldTypeIdentifier(),
            'required' => $this->isFieldRequired($fieldDef),
            'translatable' => (bool) $fieldDef->isTranslatable(),
        ];

        $description = trim((string) $fieldDef->getDescription());
        if ($description !== '') {
            $info['description'] = $description;
        }

        switch ($fieldDef->getFieldTypeIdentifier()) {
            case FieldTypes::MATRIX:
                $columns = [];
                foreach ((array) ($fieldDef->getFieldSettings()['columns'] ?? []) as $column) {
                    $columns[] = [
                        'identifier' => (string) ($column['identifier'] ?? ''),
                        'name' => (string) ($column['name'] ?? ''),
                    ];
                }
                $info['columns'] = $columns;
                $this->appendMatrixRowLimits($info, $fieldDef);
                break;

            case FieldTypes::OBJECTRELATIONLIST:
                $info['allowedTypes'] = array_values(
                    (array) ($fieldDef->getFieldSettings()['selectionContentTypes'] ?? [])
                );
                $this->appendRelationListItemLimits($info, $fieldDef);
                break;

            case FieldTypes::SELECTION:
                $info['options'] = array_values(
                    (array) ($fieldDef->getFieldSettings()['options'] ?? [])
                );
                break;

            case FieldTypes::STRING:
            case FieldTypes::TEXT:
                $this->appendStringConstraints($info, $fieldDef);
                break;

            case FieldTypes::IMAGE:
                $this->appendImageConstraints($info, $fieldDef);
                break;
        }

        return $info;
    }

    /**
     * Determine whether a field is required based on its definition and type.
     *
     * Strategy (in priority order):
     *   1. Trust the content type's isRequired flag (set by Ibexa's mapper).
     *   2. ezimage is always required — there is no "empty" image value.
     *   3. Fall back to validator configuration (StringLength min > 0,
     *      minimum row count, minimum relation limit, etc.).
     */
    private function isFieldRequired(FieldDefinition $fieldDef): bool
    {
        if ($fieldDef->isRequired()) {
            return true;
        }

        if ($fieldDef->getFieldTypeIdentifier() === FieldTypes::IMAGE) {
            return true;
        }

        $validator = $fieldDef->getValidatorConfiguration();

        if (isset($validator['StringLengthValidator']['minStringLength'])
            && (int) $validator['StringLengthValidator']['minStringLength'] > 0) {
            return true;
        }

        if (isset($validator['MatrixValueValidator']['minimumRowCount'])
            && (int) $validator['MatrixValueValidator']['minimumRowCount'] > 0) {
            return true;
        }

        if (isset($validator['RelationValidator']['minimumRelationLimit'])
            && (int) $validator['RelationValidator']['minimumRelationLimit'] > 0) {
            return true;
        }

        return false;
    }

    /**
     * Build the full field schema map for a content type.
     *
     * @return array<string, array<string, mixed>>
     */
    public function forType(ContentType $contentType): array
    {
        $fields = [];
        foreach ($contentType->getFieldDefinitions() as $fieldDef) {
            $fields[$fieldDef->getIdentifier()] = $this->forField($fieldDef);
        }

        return $fields;
    }

    /**
     * @param array<string, mixed> $info
     */
    private function appendStringConstraints(array &$info, FieldDefinition $fieldDef): void
    {
        $v = $fieldDef->getValidatorConfiguration()['StringLengthValidator'] ?? null;
        if (!is_array($v)) {
            return;
        }
        $min = isset($v['minStringLength']) ? (int) $v['minStringLength'] : 0;
        $max = isset($v['maxStringLength']) ? (int) $v['maxStringLength'] : 0;
        if ($min > 0) {
            $info['minLength'] = $min;
        }
        if ($max > 0) {
            $info['maxLength'] = $max;
        }
    }

    /**
     * @param array<string, mixed> $info
     */
    private function appendImageConstraints(array &$info, FieldDefinition $fieldDef): void
    {
        $v = $fieldDef->getValidatorConfiguration();
        if (isset($v['FileSizeValidator']['maxFileSize'])
            && (int) $v['FileSizeValidator']['maxFileSize'] > 0) {
            $info['maxFileSize'] = (int) $v['FileSizeValidator']['maxFileSize'];
        }
        if (!empty($v['AlternativeTextValidator']['required'])) {
            $info['altTextRequired'] = true;
        }
    }

    /**
     * @param array<string, mixed> $info
     */
    private function appendMatrixRowLimits(array &$info, FieldDefinition $fieldDef): void
    {
        $v = $fieldDef->getValidatorConfiguration()['MatrixValueValidator'] ?? null;
        if (!is_array($v)) {
            return;
        }
        if (isset($v['minimumRowCount']) && (int) $v['minimumRowCount'] > 0) {
            $info['minRows'] = (int) $v['minimumRowCount'];
        }
        if (isset($v['maximumRowCount']) && (int) $v['maximumRowCount'] > 0) {
            $info['maxRows'] = (int) $v['maximumRowCount'];
        }
    }

    /**
     * @param array<string, mixed> $info
     */
    private function appendRelationListItemLimits(array &$info, FieldDefinition $fieldDef): void
    {
        $v = $fieldDef->getValidatorConfiguration()['RelationValidator'] ?? null;
        if (!is_array($v)) {
            return;
        }
        if (isset($v['minimumRelationLimit']) && (int) $v['minimumRelationLimit'] > 0) {
            $info['minItems'] = (int) $v['minimumRelationLimit'];
        }
        if (isset($v['maximumRelationLimit']) && (int) $v['maximumRelationLimit'] > 0) {
            $info['maxItems'] = (int) $v['maximumRelationLimit'];
        }
    }
}
