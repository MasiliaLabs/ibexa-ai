<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Field\Stringifier;

use Ibexa\Contracts\Core\Repository\Values\Content\Field;
use Ibexa\Contracts\Core\Repository\Values\ContentType\FieldDefinition;
use Masilia\Bundle\IbexaAi\Support\FieldType;
use Masilia\Bundle\IbexaAi\Field\FieldValueStringifierInterface;

final class MapLocationStringifier implements FieldValueStringifierInterface
{
    public static function getSupportedFieldTypes(): array
    {
        return [FieldType::EZGMAPLOCATION];
    }

    public function toString(Field $field, FieldDefinition $fieldDefinition): string
    {
        if ($field->value === null) {
            return '';
        }

        $value = $field->value;

        $parts = array_filter([
            $value->address ?? null,
            ($value->latitude ?? null) !== null ? "lat:{$value->latitude}" : null,
            ($value->longitude ?? null) !== null ? "lon:{$value->longitude}" : null,
        ]);

        return implode(', ', $parts);
    }
}
