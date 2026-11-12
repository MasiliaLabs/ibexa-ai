<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Field\Stringifier;

use Ibexa\Contracts\Core\Repository\ContentService;
use Ibexa\Contracts\Core\Repository\Values\Content\Field;
use Ibexa\Contracts\Core\Repository\Values\ContentType\FieldDefinition;
use Masilia\Bundle\IbexaAi\Support\FieldType;
use Masilia\Bundle\IbexaAi\Field\FieldValueStringifierInterface;
use Throwable;

final readonly class RelationStringifier implements FieldValueStringifierInterface
{
    public function __construct(
        private ContentService $contentService,
    )
    {
    }

    public static function getSupportedFieldTypes(): array
    {
        return [FieldType::EZOBJECTRELATION];
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
