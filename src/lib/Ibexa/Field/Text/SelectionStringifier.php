<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Ibexa\Field\Text;

use Ibexa\Contracts\Core\Repository\Values\Content\Field;
use Ibexa\Contracts\Core\Repository\Values\ContentType\FieldDefinition;
use Masilia\IbexaAi\Ibexa\Field\FieldTypes;
use Masilia\IbexaAi\Ibexa\Field\Text\FieldValueStringifier;

/**
 * ezselection: options = [0 => 'Label A', 1 => 'Label B', ...],
 *              selection = [0, 2] (selected numeric indices).
 */
final class SelectionStringifier implements FieldValueStringifier
{
    public static function getSupportedFieldTypes(): array
    {
        return [FieldTypes::SELECTION];
    }

    public function toString(Field $field, FieldDefinition $fieldDefinition): string
    {
        if ($field->getValue() === null) {
            return '';
        }

        $options = $fieldDefinition->getFieldSettings()['options'] ?? [];
        $selected = $field->getValue()->selection ?? [];
        $labels = [];

        foreach ($selected as $index) {
            if (isset($options[$index])) {
                $labels[] = $options[$index];
            }
        }

        return implode(', ', $labels);
    }
}
