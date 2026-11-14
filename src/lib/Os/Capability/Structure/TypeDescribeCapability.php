<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Os\Capability\Structure;

use Masilia\Contracts\IbexaAi\Os\CapabilityResult;
use Masilia\Contracts\IbexaAi\Os\Risk;

final class TypeDescribeCapability extends TypeCapability
{
    public function name(): string
    {
        return 'structure.type.describe';
    }

    public function summary(): string
    {
        return 'Describe a content type: its fields, settings and schemas.';
    }

    public function description(): string
    {
        return 'Returns the type with every field (identifier, field type, name, required, translatable, searchable, position, settings), the name and URL alias schemas, and whether contents of the type exist.';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'required' => ['type'], 'properties' => ['type' => ['type' => 'string', 'minLength' => 1, 'description' => 'Identifier of the content type']]];
    }

    public function risk(): Risk
    {
        return Risk::READ;
    }

    public function requires(): array
    {
        return [['content', 'read']];
    }

    public function examples(): array
    {
        return [['description' => 'The fields of the article type', 'arguments' => ['type' => 'article']]];
    }

    public function validate(array $arguments): ?string
    {
        return $this->missingType((string) $arguments['type']);
    }

    public function execute(array $arguments): CapabilityResult
    {
        $type = $this->types()->loadContentTypeByIdentifier((string) $arguments['type']);

        return CapabilityResult::ok(sprintf('Content type "%s"', $type->getName()), [
            'identifier' => $type->identifier,
            'name' => $type->getName(),
            'container' => $type->isContainer,
            'main_language' => $type->mainLanguageCode,
            'name_schema' => $type->nameSchema,
            'url_alias_schema' => $type->urlAliasSchema,
            'in_use' => $this->types()->isContentTypeUsed($type),
            'groups' => array_map(static fn ($g): string => $g->identifier, $type->getContentTypeGroups()),
            'fields' => array_map(self::describeField(...), iterator_to_array($type->getFieldDefinitions(), false)),
        ]);
    }
}
