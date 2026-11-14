<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Ibexa\Field\Input;

use Ibexa\Contracts\Core\Repository\Values\ContentType\FieldDefinition;
use Masilia\IbexaAi\Ibexa\Field\Input\FieldInput;
use Novactive\Bundle\eZSEOBundle\Core\FieldType\Metas;
use Novactive\Bundle\eZSEOBundle\Core\FieldType\Metas\Value;
use Novactive\Bundle\eZSEOBundle\Core\Meta;

/**
 * Converts what the model gives into a novaseometas Value: a JSON object with the meta names as keys and their content as values,
 *   { "title": "Page Title", "description": "A description", "og:image": "/var/site/storage/images/logo.jpg" }
 * Each one becomes a Meta of the field, as the editing form of Nova SEO stores it. The content of a meta is kept as it is given:
 * choosing an image is the editor's, this does not search the library for one.
 *
 * Only registered when the Nova SEO bundle is installed (see MasiliaIbexaAiExtension).
 */
readonly class NovaSeoMetasInput implements FieldInput
{
    public function getFieldTypeIdentifier(): string
    {
        return Metas\Type::IDENTIFIER;
    }

    public function transform(FieldDefinition $fieldDef, mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        $metas = [];
        foreach ($value as $metaName => $metaContent) {
            if (!is_string($metaName)) {
                continue;
            }

            $meta = new Meta();
            $meta->setName($metaName);
            $meta->setContent(is_string($metaContent) ? $metaContent : '');
            $meta->setFieldType('text');
            $metas[] = $meta;
        }

        return new Value($metas);
    }
}
