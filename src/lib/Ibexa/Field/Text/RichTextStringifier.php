<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Ibexa\Field\Text;

use DOMDocument;
use Ibexa\FieldTypeRichText\RichText\TextExtractor\FullTextExtractor;
use Ibexa\Contracts\Core\Repository\Values\Content\Field;
use Ibexa\Contracts\Core\Repository\Values\ContentType\FieldDefinition;
use Masilia\IbexaAi\Ibexa\Field\FieldTypes;
use Masilia\IbexaAi\Ibexa\Field\Text\FieldValueStringifier;

final class RichTextStringifier implements FieldValueStringifier
{
    public function __construct(private readonly FullTextExtractor $extractor = new FullTextExtractor())
    {
    }

    public static function getSupportedFieldTypes(): array
    {
        return [FieldTypes::RICHTEXT];
    }

    public function toString(Field $field, FieldDefinition $fieldDefinition): string
    {
        $value = $field->getValue();

        if ($value === null || !property_exists($value, 'xml') || !$value->xml instanceof DOMDocument) {
            return '';
        }

        // The text of the document as Ibexa's own search index reads it, one space between the texts of two blocks.
        return trim((string) preg_replace('/\s+/', ' ', $this->extractor->extractText($value->xml)));
    }
}
