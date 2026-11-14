<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Ibexa\Field\Text;

use DOMDocument;
use Ibexa\Contracts\Core\Repository\Values\Content\Field;
use Ibexa\Contracts\Core\Repository\Values\ContentType\FieldDefinition;
use Masilia\Bundle\IbexaAi\Ibexa\Field\FieldTypes;
use Masilia\Bundle\IbexaAi\Ibexa\Field\Text\FieldValueStringifier;

final class RichTextStringifier implements FieldValueStringifier
{
    public static function getSupportedFieldTypes(): array
    {
        return [FieldTypes::RICHTEXT];
    }

    public function toString(Field $field, FieldDefinition $fieldDefinition): string
    {
        $value = $field->getValue();

        if ($value === null || !property_exists($value, 'xml') || !$value->xml instanceof DOMDocument) {
            return '';
        }

        return trim($value->xml->documentElement?->textContent ?? '');
    }
}
