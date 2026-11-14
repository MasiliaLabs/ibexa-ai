<?php

declare(strict_types=1);

namespace Masilia\Contracts\IbexaAi\Content;

use Ibexa\Contracts\Core\Repository\Values\Content\Field;
use Ibexa\Contracts\Core\Repository\Values\ContentType\FieldDefinition;

/** What the value of a field says, as plain text a model can read (a rich text without its markup, a relation by the name of its target…). */
interface FieldTexts
{
    public function toString(Field $field, FieldDefinition $fieldDefinition): string;
}
