<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Ibexa\Field\Input;

use Ibexa\Contracts\Core\Repository\Values\ContentType\FieldDefinition;
use Ibexa\Core\FieldType\Url\Value as UrlValue;
use Masilia\Bundle\IbexaAi\Ibexa\Field\Input\FieldInput;

/**
 * Normalizes LLM output into the format expected by ezurl fields.
 *
 * Accepts: a URL string or {link, text} array.
 * Returns: UrlValue object.
 */
readonly class UrlInput implements FieldInput
{
    public function getFieldTypeIdentifier(): string
    {
        return 'ibexa_url';
    }

    public function transform(FieldDefinition $fieldDef, mixed $value): mixed
    {
        if ($value instanceof UrlValue) {
            return $value;
        }

        if (is_string($value)) {
            return new UrlValue($value, $value);
        }

        if (is_array($value) && isset($value['link'])) {
            return new UrlValue($value['link'], $value['text'] ?? $value['link']);
        }

        return $value;
    }
}
