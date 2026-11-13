<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Os\Capability\Structure;

use Masilia\IbexaAi\Os\CapabilityResult;
use Masilia\IbexaAi\Os\Preview;
use Masilia\IbexaAi\Os\Risk;

final class FieldRemoveCapability extends TypeCapability
{
    public function name(): string
    {
        return 'structure.field.remove';
    }

    public function summary(): string
    {
        return 'Remove a field from a content type, and its data from every content.';
    }

    public function description(): string
    {
        return 'Removes the field definition. The values stored in it, in every content of the type, are deleted for good.';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'required' => ['type', 'field'], 'properties' => ['type' => ['type' => 'string', 'minLength' => 1, 'description' => 'Identifier of the content type'], 'field' => ['type' => 'string', 'minLength' => 1, 'description' => 'Identifier of the field']]];
    }

    public function risk(): Risk
    {
        return Risk::DESTRUCTIVE;
    }

    public function requires(): array
    {
        return [['class', 'update']];
    }

    public function examples(): array
    {
        return [['description' => 'Remove a field', 'arguments' => ['type' => 'landing_page', 'field' => 'summary']]];
    }

    public function validate(array $arguments): ?string
    {
        if (($problem = $this->missingType((string) $arguments['type'])) !== null) {
            return $problem;
        }
        $type = $this->types()->loadContentTypeByIdentifier((string) $arguments['type']);

        return $type->getFieldDefinition((string) $arguments['field']) === null ? sprintf('The type "%s" has no field "%s" (it has: %s).', $arguments['type'], $arguments['field'], implode(', ', array_map(static fn ($f): string => $f->identifier, iterator_to_array($type->getFieldDefinitions(), false)))) : null;
    }

    public function preview(array $arguments): Preview
    {
        return new Preview('Remove a field', [['Type', (string) $arguments['type']], ['Field', (string) $arguments['field']]], array_merge(['The field and the values stored in it are deleted for good. This cannot be undone.'], $this->usedNote((string) $arguments['type'])));
    }

    public function execute(array $arguments): CapabilityResult
    {
        $service = $this->types();
        $this->edit((string) $arguments['type'], static function ($draft) use ($service, $arguments): void {
            $service->removeFieldDefinition($draft, $draft->getFieldDefinition((string) $arguments['field']));
        });

        return CapabilityResult::ok(sprintf('Field "%s" removed from "%s"', $arguments['field'], $arguments['type']), ['type' => $arguments['type']]);
    }
}
