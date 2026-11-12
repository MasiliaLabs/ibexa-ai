<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Field\Stringifier;

use DOMDocument;
use Ibexa\Contracts\Core\Repository\Values\Content\Field;
use Ibexa\Contracts\Core\Repository\Values\ContentType\FieldDefinition;
use Masilia\Bundle\IbexaAi\Support\FieldType;
use Masilia\Bundle\IbexaAi\Field\FieldValueStringifierInterface;

final class RichTextStringifier implements FieldValueStringifierInterface
{
    public static function getSupportedFieldTypes(): array
    {
        return [FieldType::EZRICHTEXT];
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
