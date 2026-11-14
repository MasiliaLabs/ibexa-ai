<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Ibexa\Field\Text;

use Ibexa\Contracts\Core\Repository\Values\Content\Field;
use Ibexa\Contracts\Core\Repository\Values\ContentType\FieldDefinition;
use Masilia\Bundle\IbexaAi\Ibexa\Field\FieldTypes;
use Masilia\Bundle\IbexaAi\Ibexa\Field\Text\FieldValueStringifier;

final class CountryStringifier implements FieldValueStringifier
{
    public static function getSupportedFieldTypes(): array
    {
        return [FieldTypes::COUNTRY];
    }

    public function toString(Field $field, FieldDefinition $fieldDefinition): string
    {
        if ($field->getValue() === null) {
            return '';
        }

        $countries = $field->getValue()->countries ?? [];

        return implode(', ', array_column($countries, 'Name'));
    }
}
