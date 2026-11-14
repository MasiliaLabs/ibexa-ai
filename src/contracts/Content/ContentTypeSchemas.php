<?php

declare(strict_types=1);

namespace Masilia\Contracts\IbexaAi\Content;

use Ibexa\Contracts\Core\Repository\Values\ContentType\ContentType;

/**
 * What a content type looks like to the assistant: for each field, its type, whether it is required and translatable, and
 * what the type needs to know to fill it (the columns of a matrix, the types a relation list accepts, the options of a
 * selection, limits). The same description the assistant reads, so that a package describes types the way it does.
 *
 * A schema is {identifier: string, name: string, fields: {<field identifier>: {type: string, required: bool, translatable:
 * bool, description?: string, columns?: list<{identifier, name}>, allowedTypes?: list<string>, options?: list<string>, …}}}.
 */
interface ContentTypeSchemas
{
    /**
     * @return array{identifier: string, name: string, fields: array<string, array<string, mixed>>}|null Null for a type that does not exist
     */
    public function schema(string $identifier): ?array;

    /**
     * The fields of a content type already loaded.
     *
     * @return array<string, array<string, mixed>> Field identifier => its description
     */
    public function fields(ContentType $type): array;

    /**
     * @return list<string> The identifiers of the content types that exist, sorted
     */
    public function knownTypes(): array;
}
