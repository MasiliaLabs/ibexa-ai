<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Field\Stringifier;

use Ibexa\Contracts\Core\Repository\Values\Content\Field;
use Ibexa\Contracts\Core\Repository\Values\ContentType\FieldDefinition;
use Masilia\Bundle\IbexaAi\Support\FieldType;
use Masilia\Bundle\IbexaAi\Field\FieldValueStringifierInterface;

final class CountryStringifier implements FieldValueStringifierInterface
{
    public static function getSupportedFieldTypes(): array
    {
        return [FieldType::EZCOUNTRY];
    }

    public function toString(Field $field, FieldDefinition $fieldDefinition): string
    {
        if ($field->value === null) {
            return '';
        }

        $countries = $field->value->countries ?? [];

        return implode(', ', array_column($countries, 'Name'));
    }
}
