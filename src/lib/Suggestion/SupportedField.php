<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Suggestion;

/**
 * Field types that accept AI suggestions in this version.
 *
 * Identifiers are the Ibexa 5 field type identifiers (underscored form).
 * ezmatrix and novaseometas are not supported yet: they need their own
 * prompt context (row/column schema, SEO meta keys).
 */
enum SupportedField: string
{
    case String = 'ibexa_string';
    case Text = 'ibexa_text';
    case RichText = 'ibexa_richtext';
    case Image = 'ibexa_image';

    public static function fromIdentifier(string $identifier): self
    {
        return self::tryFrom($identifier)
            ?? throw new InvalidSuggestionRequestException(sprintf(
                'Field type "%s" does not support AI suggestions yet.',
                $identifier,
            ));
    }

    public function format(): FieldFormat
    {
        return match ($this) {
            self::String, self::Image => FieldFormat::PlainText,
            self::Text => FieldFormat::TextBlock,
            self::RichText => FieldFormat::Html,
        };
    }
}
