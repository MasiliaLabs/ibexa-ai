<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Os\Capability\Structure;

use Masilia\IbexaAi\Os\CapabilityResult;
use Masilia\IbexaAi\Os\Risk;

final class TypeListCapability extends TypeCapability
{
    public function name(): string
    {
        return 'structure.type.list';
    }

    public function summary(): string
    {
        return 'List the content types, optionally of one group.';
    }

    public function description(): string
    {
        return 'Returns each content type with its name, groups, whether it is a container, and its fields (identifier:field type). Use structure.type.describe for the details of one.';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'required' => [], 'properties' => ['group' => ['type' => 'string', 'description' => 'Only the types of this group']]];
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
        return [['description' => 'Types of the Content group', 'arguments' => ['group' => 'Content']]];
    }

    public function validate(array $arguments): ?string
    {
        return isset($arguments['group']) ? $this->missingGroup((string) $arguments['group']) : null;
    }

    public function execute(array $arguments): CapabilityResult
    {
        $service = $this->types();
        $groups = isset($arguments['group']) ? [$service->loadContentTypeGroupByIdentifier((string) $arguments['group'])] : iterator_to_array($service->loadContentTypeGroups(), false);
        $rows = [];
        foreach ($groups as $group) {
            foreach ($service->loadContentTypes($group) as $type) {
                $rows[$type->identifier] ??= [
                    'identifier' => $type->identifier,
                    'name' => $type->getName(),
                    'container' => $type->isContainer,
                    'groups' => array_map(static fn ($g): string => $g->identifier, $type->getContentTypeGroups()),
                    'fields' => array_map(static fn ($f): string => $f->identifier . ':' . $f->fieldTypeIdentifier, iterator_to_array($type->getFieldDefinitions(), false)),
                ];
            }
        }

        return CapabilityResult::ok(sprintf('%d content type(s)', count($rows)), ['types' => array_values($rows)]);
    }
}
