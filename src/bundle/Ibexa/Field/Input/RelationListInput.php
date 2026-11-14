<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Ibexa\Field\Input;

use Ibexa\Contracts\Core\Repository\Values\ContentType\FieldDefinition;
use Masilia\Bundle\IbexaAi\Ibexa\Field\Input\FieldInput;
use Masilia\Bundle\IbexaAi\Ibexa\Field\FieldTypes;

/**
 * Normalizes LLM output into the format expected by ezobjectrelationlist fields.
 *
 * Accepts: array of content IDs or ['destinationContentIds' => [...]].
 * Returns: array of content IDs ( Ibexa accepts a flat array for relation lists).
 */
readonly class RelationListInput implements FieldInput
{
    public function getFieldTypeIdentifier(): string
    {
        return FieldTypes::OBJECTRELATIONLIST;
    }

    public function transform(FieldDefinition $fieldDef, mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        // Already wrapped: ['destinationContentIds' => [...]]
        if (isset($value['destinationContentIds']) && is_array($value['destinationContentIds'])) {
            return $value['destinationContentIds'];
        }

        // Flatten nested arrays (e.g. [[1176], [1177]] → [1176, 1177])
        $flat = [];
        foreach ($value as $entry) {
            if (is_array($entry)) {
                array_push($flat, ...$entry);
            } else {
                $flat[] = $entry;
            }
        }

        return $flat;
    }
}
