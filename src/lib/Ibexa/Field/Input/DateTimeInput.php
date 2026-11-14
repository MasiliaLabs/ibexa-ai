<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Ibexa\Field\Input;

use DateTime;
use Ibexa\Contracts\Core\Repository\Values\ContentType\FieldDefinition;
use Masilia\IbexaAi\Ibexa\Field\Input\FieldInput;
use Throwable;

/**
 * Normalizes LLM output into the format expected by ezdatetime fields.
 *
 * Accepts: an ISO 8601 datetime string (e.g. "2026-06-14T10:00:00").
 * Returns: a DateTime object (Ibexa's createValueFromInput() accepts this).
 */
readonly class DateTimeInput implements FieldInput
{
    public function getFieldTypeIdentifier(): string
    {
        return 'ibexa_datetime';
    }

    public function transform(FieldDefinition $fieldDef, mixed $value): mixed
    {
        if (is_string($value) && $value !== '') {
            try {
                return new DateTime($value);
            } catch (Throwable) {
                return $value;
            }
        }

        return $value;
    }
}
