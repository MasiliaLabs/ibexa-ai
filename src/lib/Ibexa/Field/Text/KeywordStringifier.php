<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Ibexa\Field\Text;

use Ibexa\Contracts\Core\Repository\Values\Content\Field;
use Ibexa\Contracts\Core\Repository\Values\ContentType\FieldDefinition;
use Masilia\IbexaAi\Ibexa\Field\FieldTypes;
use Masilia\IbexaAi\Ibexa\Field\Text\FieldValueStringifier;

final class KeywordStringifier implements FieldValueStringifier
{
    public static function getSupportedFieldTypes(): array
    {
        return [FieldTypes::KEYWORD];
    }

    public function toString(Field $field, FieldDefinition $fieldDefinition): string
    {
        if ($field->getValue() === null) {
            return '';
        }

        return implode(', ', $field->getValue()->values ?? []);
    }
}
