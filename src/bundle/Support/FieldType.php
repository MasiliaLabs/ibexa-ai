<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Support;

/**
 * Canonical identifiers for every Ibexa field type the AI assistant
 * touches. Mirrors {@see \Masilia\Bundle\IbexaAi\Client\ProviderId}: bare
 * string constants so that stringly-typed branches like
 * `if ($ctx->fieldType === 'ezmatrix')` become
 * `if ($ctx->fieldType === FieldType::Ezmatrix)`.
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
 * {@see FieldValueStringifierInterface::getSupportedFieldTypes()}
 * returns). Drift here breaks stringifier dispatch.
 */
final class FieldType
{
    public const EZSTRING = 'ibexa_string';
    public const EZTEXT = 'ibexa_text';
    public const EZRICHTEXT = 'ibexa_richtext';
    public const NOVASEOMETAS = 'novaseometas';
    public const EZMATRIX = 'ibexa_matrix';

    public const EZAUTHOR = 'ibexa_author';
    public const EZCOUNTRY = 'ibexa_country';
    public const EZIMAGE = 'ibexa_image';
    public const EZIMAGEASSET = 'ibexa_image_asset';
    public const EZBINARYFILE = 'ibexa_binary_file';
    public const EZMEDIA = 'ibexa_media';
    public const EZKEYWORD = 'ibexa_keyword';
    public const EZGMAPLOCATION = 'ibexa_gmap_location';
    public const EZOBJECTRELATION = 'ibexa_object_relation';
    public const EZOBJECTRELATIONLIST = 'ibexa_object_relation_list';
    public const EZSELECTION = 'ibexa_selection';
    public const EZTAGS = 'ibexa_taxonomy_entry_assignment';

    /**
     * Field types the AI modal exposes the "AI" button on.
     *
     * @return list<string>
     */
    public static function aiTargeted(): array
    {
        return [
            self::EZSTRING,
            self::EZTEXT,
            self::EZRICHTEXT,
            self::NOVASEOMETAS,
            self::EZMATRIX,
            self::EZIMAGE,
        ];
    }
}
