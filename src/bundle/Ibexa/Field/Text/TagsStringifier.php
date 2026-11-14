<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Ibexa\Field\Text;

use Ibexa\Contracts\Core\Repository\Values\Content\Field;
use Ibexa\Contracts\Core\Repository\Values\ContentType\FieldDefinition;
use Masilia\Bundle\IbexaAi\Ibexa\Field\FieldTypes;
use Masilia\Bundle\IbexaAi\Ibexa\Field\Text\FieldValueStringifier;

final class TagsStringifier implements FieldValueStringifier
{
    public static function getSupportedFieldTypes(): array
    {
        return [FieldTypes::TAGS];
    }

    public function toString(Field $field, FieldDefinition $fieldDefinition): string
    {
        $value = $field->getValue();

        if (!property_exists($value, 'tags') || !is_iterable($value->tags)) {
            return '';
        }

        $keywords = [];
        foreach ($value->tags as $tag) {
            $keyword = $tag->getKeyword() ?? '';
            if ($keyword !== '') {
                $keywords[] = $keyword;
            }
        }

        return implode(', ', $keywords);
    }
}
