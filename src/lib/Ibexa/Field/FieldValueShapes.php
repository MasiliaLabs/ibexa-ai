<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Ibexa\Field;

use Ibexa\Contracts\Core\Repository\Values\ContentType\FieldDefinition;

/**
 * The value a model gives for a field of each type: a JSON Schema and an example. It is what the field adapters
 * (Input\FieldInput) accept; it is told to the model in the description of every field, and in the system prompt for the types
 * that have no setting to read.
 */
final class FieldValueShapes
{
    /**
     * @return array{schema: array<string, mixed>, example: mixed}|null Null for a field type the assistant does not know: it is given as Ibexa's own hash
     */
    public static function for(FieldDefinition $field): ?array
    {
        $settings = $field->getFieldSettings();

        return match ($field->getFieldTypeIdentifier()) {
            FieldTypes::SELECTION => self::selection(array_values((array) ($settings['options'] ?? []))),
            FieldTypes::MATRIX => self::matrix((array) ($settings['columns'] ?? [])),
            FieldTypes::OBJECTRELATIONLIST => [
                'schema' => ['type' => 'array', 'items' => ['type' => 'integer', 'minimum' => 1], 'description' => 'Content ids, in order'],
                'example' => [1176, 1177],
            ],
            default => self::fixed($field->getFieldTypeIdentifier()),
        };
    }

    /**
     * What each type takes, in a sentence, for the system prompt: only the types whose value does not depend on the field's settings.
     *
     * @return array<string, string>
     */
    public static function documented(): array
    {
        $texts = [];
        foreach (self::FIXED as $type => [$description]) {
            $texts[$type] = $description;
        }

        return $texts;
    }

    /** @var array<string, array{0: string, 1: array<string, mixed>, 2: mixed}> type => [description, JSON Schema, example] */
    private const FIXED = [
        'ibexa_string' => ['plain text.', ['type' => 'string'], 'Welcome'],
        'ibexa_text' => ['plain text, line breaks allowed.', ['type' => 'string'], "First line\nSecond line"],
        'ibexa_email' => ['an e-mail address.', ['type' => 'string', 'format' => 'email'], 'contact@example.org'],
        'ibexa_richtext' => ['an HTML string such as "<p>Hello</p>". Never DocBook XML.', ['type' => 'string', 'description' => 'HTML'], '<p>Hello <strong>world</strong></p>'],
        'ibexa_image' => [
            'a description of the image to make ("a photo of a sunset over the ocean") or an object {description, size?, quality?} (size "1024x1024", "1792x1024", "1024x1792", "16:9"… ; quality "standard" or "hd"). The image is generated from the description, or taken from an attachment_id.',
            ['oneOf' => [['type' => 'string'], ['type' => 'object', 'required' => ['description'], 'properties' => ['description' => ['type' => 'string'], 'size' => ['type' => 'string'], 'quality' => ['type' => 'string']]]]],
            'a photo of a sunset over the ocean',
        ],
        'ibexa_author' => [
            'an author or a list of authors: "Name <email>", an e-mail address, or {name, email}. The sender and the recipients of a form take one.',
            ['oneOf' => [['type' => 'string'], ['type' => 'object', 'properties' => ['name' => ['type' => 'string'], 'email' => ['type' => 'string', 'format' => 'email']]], ['type' => 'array', 'items' => ['oneOf' => [['type' => 'string'], ['type' => 'object']]]]]],
            'Contact <contact@example.org>',
        ],
        'ibexa_keyword' => ['a comma-separated string ("acme, exit") or a list of words.', ['oneOf' => [['type' => 'string'], ['type' => 'array', 'items' => ['type' => 'string']]]], 'acme, exit, environment'],
        'ibexa_gmap_location' => ['{latitude, longitude, address} (also lat / lng).', ['type' => 'object', 'required' => ['latitude', 'longitude'], 'properties' => ['latitude' => ['type' => 'number'], 'longitude' => ['type' => 'number'], 'address' => ['type' => 'string']]], ['latitude' => 33.5731, 'longitude' => -7.5898, 'address' => 'Casablanca']],
        'ibexa_object_relation' => ['the integer id of the target content.', ['type' => 'integer', 'minimum' => 1], 1176],
        'ibexa_url' => ['a URL string, or {link, text}.', ['oneOf' => [['type' => 'string', 'format' => 'uri'], ['type' => 'object', 'required' => ['link'], 'properties' => ['link' => ['type' => 'string'], 'text' => ['type' => 'string']]]]], ['link' => 'https://example.org', 'text' => 'Our site']],
        'ibexa_date' => ['an ISO 8601 date ("2026-06-14").', ['type' => 'string', 'format' => 'date'], '2026-06-14'],
        'ibexa_datetime' => ['an ISO 8601 date and time ("2026-06-14T10:00:00").', ['type' => 'string', 'format' => 'date-time'], '2026-06-14T10:00:00'],
        'ibexa_boolean' => ['true or false.', ['type' => 'boolean'], true],
        'ibexa_integer' => ['a whole number.', ['type' => 'integer'], 12],
        'ibexa_float' => ['a number.', ['type' => 'number'], 1.5],
        'novaseometas' => ['{title, description, "og:image"?}: the SEO metas of the page.', ['type' => 'object', 'properties' => ['title' => ['type' => 'string'], 'description' => ['type' => 'string'], 'og:image' => ['type' => 'string']]], ['title' => 'Our work', 'description' => 'What we do for the climate.']],
    ];

    /**
     * @return array{schema: array<string, mixed>, example: mixed}|null
     */
    private static function fixed(string $type): ?array
    {
        $shape = self::FIXED[$type] ?? null;

        return $shape === null ? null : ['schema' => $shape[1], 'example' => $shape[2]];
    }

    /**
     * @param list<string> $options
     *
     * @return array{schema: array<string, mixed>, example: mixed}
     */
    private static function selection(array $options): array
    {
        return [
            'schema' => ['oneOf' => [['type' => 'string', 'enum' => $options], ['type' => 'integer', 'minimum' => 0, 'description' => 'The index of an option']], 'description' => 'The label of an option'],
            'example' => $options[0] ?? 'Option',
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $columns
     *
     * @return array{schema: array<string, mixed>, example: mixed}
     */
    private static function matrix(array $columns): array
    {
        $identifiers = array_values(array_filter(array_map(static fn (array $column): string => (string) ($column['identifier'] ?? ''), $columns)));
        $row = array_fill_keys($identifiers, 'text');

        return [
            'schema' => ['type' => 'array', 'items' => ['type' => 'object', 'required' => $identifiers, 'properties' => array_map(static fn (): array => ['type' => 'string'], array_flip($identifiers)), 'additionalProperties' => false], 'description' => 'One object per row, with exactly these columns'],
            'example' => $row === [] ? [] : [$row],
        ];
    }
}
