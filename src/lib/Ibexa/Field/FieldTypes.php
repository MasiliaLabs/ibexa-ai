<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Ibexa\Field;

/**
 * Canonical identifiers for every Ibexa field type the AI assistant
 * touches. Mirrors {@see \Masilia\Bundle\IbexaAi\Client\ProviderId}: bare
 * string constants so that stringly-typed branches like
 * `if ($ctx->fieldType === 'ezmatrix')` become
 * `if ($ctx->fieldType === FieldTypes::Ezmatrix)`.
 *
 * Two groups of field types live here:
 *  - **AI-targeted** (the 6 the modal can act on directly):
 *    {@see EZSTRING}, {@see EZTEXT}, {@see EZRICHTEXT},
 *    {@see NOVASEOMETAS}, {@see EZMATRIX}, {@see EZIMAGE}. These are the field types
 *    the React SPA exposes the "AI" button on.
 *  - **Sibling-field context** (the rest): the stringifiers
 *    registered against these produce readable text for the AI prompt
 *    context block, but the modal does not target them directly.
 *
 * Keep the string value in lockstep with the Ibexa core field-type
 * identifier (the string that appears in
 * `FieldDefinition::fieldTypeIdentifier` and that
 * {@see FieldValueStringifier::getSupportedFieldTypes()}
 * returns). Drift here breaks stringifier dispatch.
 */
final class FieldTypes
{
    public const STRING = 'ibexa_string';
    public const TEXT = 'ibexa_text';
    public const RICHTEXT = 'ibexa_richtext';
    public const NOVASEOMETAS = 'novaseometas';
    public const MATRIX = 'ibexa_matrix';

    public const AUTHOR = 'ibexa_author';
    public const COUNTRY = 'ibexa_country';
    public const IMAGE = 'ibexa_image';
    public const IMAGEASSET = 'ibexa_image_asset';
    public const BINARYFILE = 'ibexa_binary_file';
    public const MEDIA = 'ibexa_media';
    public const KEYWORD = 'ibexa_keyword';
    public const GMAPLOCATION = 'ibexa_gmap_location';
    public const OBJECTRELATION = 'ibexa_object_relation';
    public const OBJECTRELATIONLIST = 'ibexa_object_relation_list';
    public const SELECTION = 'ibexa_selection';
    public const TAGS = 'ibexa_taxonomy_entry_assignment';

    /**
     * Field types the AI modal exposes the "AI" button on.
     *
     * @return list<string>
     */
    public static function aiTargeted(): array
    {
        return [
            self::STRING,
            self::TEXT,
            self::RICHTEXT,
            self::NOVASEOMETAS,
            self::MATRIX,
            self::IMAGE,
        ];
    }
}
