<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Field\Stringifier;

use Ibexa\Contracts\Core\Repository\Values\Content\Field;
use Ibexa\Contracts\Core\Repository\Values\ContentType\FieldDefinition;
use Masilia\Bundle\IbexaAi\Support\FieldType;
use Masilia\Bundle\IbexaAi\Field\FieldValueStringifierInterface;

/**
 * ezselection: options = [0 => 'Label A', 1 => 'Label B', ...],
 *              selection = [0, 2] (selected numeric indices).
 */
final class SelectionStringifier implements FieldValueStringifierInterface
{
    public static function getSupportedFieldTypes(): array
    {
        return [FieldType::EZSELECTION];
    }

    public function toString(Field $field, FieldDefinition $fieldDefinition): string
    {
        if ($field->value === null) {
            return '';
        }

        $options = $fieldDefinition->getFieldSettings()['options'] ?? [];
        $selected = $field->value->selection ?? [];
        $labels = [];

        foreach ($selected as $index) {
            if (isset($options[$index])) {
                $labels[] = $options[$index];
            }
        }

        return implode(', ', $labels);
    }
}
