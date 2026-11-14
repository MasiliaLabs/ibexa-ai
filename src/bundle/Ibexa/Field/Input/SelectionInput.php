<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Ibexa\Field\Input;

use Ibexa\Contracts\Core\Repository\Values\ContentType\FieldDefinition;
use Masilia\Bundle\IbexaAi\Ibexa\Field\Input\FieldInput;
use Masilia\Bundle\IbexaAi\Ibexa\Field\FieldTypes;

/**
 * Maps ezselection label strings to option indices.
 *
 * The LLM typically outputs the human-readable label (e.g. "Dark"),
 * but Ibexa's ezselection expects an array of integer indices.
 *
 * Uses the field definition's option list (field settings) to resolve
 * labels to their index values.
 */
readonly class SelectionInput implements FieldInput
{
    public function getFieldTypeIdentifier(): string
    {
        return FieldTypes::SELECTION;
    }

    public function transform(FieldDefinition $fieldDef, mixed $value): mixed
    {
        // Already an array of indices — pass through
        if (is_array($value)) {
            return $value;
        }

        // Single integer index — wrap in array
        if (is_int($value)) {
            return [$value];
        }

        // String label — resolve against the field's option list
        // Options is an indexed array of labels: ['Dark', 'Light', 'Auto']
        if (is_string($value)) {
            $options = $fieldDef->getFieldSettings()['options'] ?? [];
            $index = array_search($value, $options, true);

            if ($index !== false) {
                return [$index];
            }

            // Label not found — return empty array so Ibexa gets a valid Selection\Value
            return [];
        }

        return [];
    }
}
