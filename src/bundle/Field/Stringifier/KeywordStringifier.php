<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Field\Stringifier;

use Ibexa\Contracts\Core\Repository\Values\Content\Field;
use Ibexa\Contracts\Core\Repository\Values\ContentType\FieldDefinition;
use Masilia\Bundle\IbexaAi\Support\FieldType;
use Masilia\Bundle\IbexaAi\Field\FieldValueStringifierInterface;

final class KeywordStringifier implements FieldValueStringifierInterface
{
    public static function getSupportedFieldTypes(): array
    {
        return [FieldType::EZKEYWORD];
    }

    public function toString(Field $field, FieldDefinition $fieldDefinition): string
    {
        if ($field->value === null) {
            return '';
        }

        return implode(', ', $field->value->values ?? []);
    }
}
