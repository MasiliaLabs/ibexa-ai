<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Ibexa\Field\Text;

use Ibexa\Contracts\Core\Repository\Values\Content\Field;
use Ibexa\Contracts\Core\Repository\Values\ContentType\FieldDefinition;

/**
 * Converts a single Ibexa field value into a compact plain-text string
 * suitable for AI prompt context.
 *
 * Each implementation declares which field-type identifiers it handles via
 * {@see getSupportedFieldTypes()}. The {@see FieldTexts}
 * indexes them for O(1) dispatch.
 */
interface FieldValueStringifier
{
    /**
     * Field-type identifiers this stringifier handles (e.g. ['ezstring', 'eztext']).
     *
     * @return string[]
     */
    public static function getSupportedFieldTypes(): array;

    /**
     * Returns a plain-text representation of the field value, or '' if empty.
     */
    public function toString(Field $field, FieldDefinition $fieldDefinition): string;
}
