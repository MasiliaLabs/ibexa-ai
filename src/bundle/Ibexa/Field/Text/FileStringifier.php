<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Ibexa\Field\Text;

use Ibexa\AdminUi\Form\DataTransformer\FieldType\ImageValueTransformer;
use Ibexa\Contracts\Core\Repository\Values\Content\Field;
use Ibexa\Contracts\Core\Repository\Values\ContentType\FieldDefinition;
use Masilia\Bundle\IbexaAi\Ibexa\Field\FieldTypes;
use Masilia\Bundle\IbexaAi\Ibexa\Field\Text\FieldValueStringifier;

final class FileStringifier implements FieldValueStringifier
{
    public static function getSupportedFieldTypes(): array
    {
        return [FieldTypes::IMAGE, FieldTypes::IMAGEASSET, FieldTypes::BINARYFILE, FieldTypes::MEDIA];
    }

    public function toString(Field $field, FieldDefinition $fieldDefinition): string
    {
        /** @var \Ibexa\Core\FieldType\Image\Value $value */
        $value = $field->getValue();

        if ($value === null || !property_exists($value, 'fileName') || !$value->fileName) {
            return '';
        }

        $fileName = (string) $value->fileName;

        if (property_exists($value, 'alternativeText') && !empty($value->alternativeText)) {
            return $fileName . ' (alt: ' . $value->alternativeText . ')';
        }

        return $fileName;
    }
}
