<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Agent\Block;

/**
 * Where the plan builder reads the schema of a content type (its fields, which are required, the columns of a matrix),
 * to refuse a change that cannot be saved before the user is asked to approve it.
 */
interface ContentTypeSchemaSource
{
    /**
     * @return array{identifier: string, name: string, fields: array<string, array<string, mixed>>}|null Null for a type that does not exist
     */
    public function schema(string $identifier): ?array;

    /**
     * @return list<string> The identifiers of the content types that exist
     */
    public function knownTypes(): array;
}
