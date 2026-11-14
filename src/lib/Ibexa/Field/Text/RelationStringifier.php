<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Ibexa\Field\Text;

use Ibexa\Contracts\Core\Repository\ContentService;
use Ibexa\Contracts\Core\Repository\Values\Content\Field;
use Ibexa\Contracts\Core\Repository\Values\ContentType\FieldDefinition;
use Masilia\IbexaAi\Ibexa\Field\FieldTypes;
use Masilia\IbexaAi\Ibexa\Field\Text\FieldValueStringifier;
use Throwable;

final readonly class RelationStringifier implements FieldValueStringifier
{
    public function __construct(
        private ContentService $contentService,
    )
    {
    }

    public static function getSupportedFieldTypes(): array
    {
        return [FieldTypes::OBJECTRELATION];
    }

    public function toString(Field $field, FieldDefinition $fieldDefinition): string
    {
        $relId = $field->getValue()->destinationContentId ?? null;

        if (!$relId) {
            return '';
        }

        try {
            return $this->contentService->loadContentInfo((int)$relId)->getName();
        } catch (Throwable) {
            return '';
        }
    }
}
