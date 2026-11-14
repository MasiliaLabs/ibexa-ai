<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Ibexa\Field\Text;

use Ibexa\Contracts\Core\Repository\Values\Content\Field;
use Ibexa\Contracts\Core\Repository\Values\ContentType\FieldDefinition;
use Masilia\IbexaAi\Ibexa\Field\FieldTypes;
use Masilia\IbexaAi\Ibexa\Field\Text\FieldValueStringifier;

final class MapLocationStringifier implements FieldValueStringifier
{
    public static function getSupportedFieldTypes(): array
    {
        return [FieldTypes::GMAPLOCATION];
    }

    public function toString(Field $field, FieldDefinition $fieldDefinition): string
    {
        if ($field->getValue() === null) {
            return '';
        }

        $value = $field->getValue();

        $parts = array_filter([
            $value->address ?? null,
            ($value->latitude ?? null) !== null ? "lat:{$value->latitude}" : null,
            ($value->longitude ?? null) !== null ? "lon:{$value->longitude}" : null,
        ]);

        return implode(', ', $parts);
    }
}
