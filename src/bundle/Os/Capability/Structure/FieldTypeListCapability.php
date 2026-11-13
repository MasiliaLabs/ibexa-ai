<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Os\Capability\Structure;

use Masilia\IbexaAi\Os\CapabilityResult;
use Masilia\IbexaAi\Os\Risk;

final class FieldTypeListCapability extends TypeCapability
{
    public function name(): string
    {
        return 'structure.field_type.list';
    }

    public function summary(): string
    {
        return 'List the field types a content type can use.';
    }

    public function description(): string
    {
        return 'Returns the identifiers of the installed field types (ibexa_string, ibexa_richtext, ibexa_image...), to use in structure.type.create and structure.field.add.';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'properties' => new \stdClass()];
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
        return [['description' => 'Available field types', 'arguments' => []]];
    }

    public function execute(array $arguments): CapabilityResult
    {
        $ids = [];
        foreach ($this->repository->getFieldTypeService()->getFieldTypes() as $identifier => $fieldType) {
            $ids[] = is_string($identifier) ? $identifier : $fieldType->getFieldTypeIdentifier();
        }
        sort($ids);

        return CapabilityResult::ok(sprintf('%d field type(s)', count($ids)), ['field_types' => $ids]);
    }
}
