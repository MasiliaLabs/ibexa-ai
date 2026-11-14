<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Os\Capability\Structure;

use Masilia\Contracts\IbexaAi\Os\CapabilityResult;
use Masilia\Contracts\IbexaAi\Os\Preview;
use Masilia\Contracts\IbexaAi\Os\Risk;

final class FieldReorderCapability extends TypeCapability
{
    public function name(): string
    {
        return 'structure.field.reorder';
    }

    public function summary(): string
    {
        return 'Change the order of the fields of a content type.';
    }

    public function description(): string
    {
        return 'Give the field identifiers in the order they must have; fields you leave out keep their relative order after them.';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'required' => ['type', 'order'], 'properties' => ['type' => ['type' => 'string', 'minLength' => 1, 'description' => 'Identifier of the content type'], 'order' => ['type' => 'array', 'minItems' => 1, 'items' => ['type' => 'string']]]];
    }

    public function risk(): Risk
    {
        return Risk::WRITE;
    }

    public function requires(): array
    {
        return [['class', 'update']];
    }

    public function examples(): array
    {
        return [['description' => 'Put the body first', 'arguments' => ['type' => 'landing_page', 'order' => ['body', 'title']]]];
    }

    public function validate(array $arguments): ?string
    {
        if (($problem = $this->missingType((string) $arguments['type'])) !== null) {
            return $problem;
        }
        $type = $this->types()->loadContentTypeByIdentifier((string) $arguments['type']);
        foreach ((array) $arguments['order'] as $identifier) {
            if ($type->getFieldDefinition((string) $identifier) === null) {
                return sprintf('The type "%s" has no field "%s".', $arguments['type'], $identifier);
            }
        }

        return null;
    }

    public function preview(array $arguments): Preview
    {
        return new Preview('Reorder fields', [['Type', (string) $arguments['type']], ['New order', implode(', ', (array) $arguments['order'])]]);
    }

    public function execute(array $arguments): CapabilityResult
    {
        $service = $this->types();
        $this->edit((string) $arguments['type'], static function ($draft) use ($service, $arguments): void {
            $order = array_values((array) $arguments['order']);
            $rest = array_values(array_diff(array_map(static fn ($f): string => $f->identifier, iterator_to_array($draft->getFieldDefinitions(), false)), $order));
            foreach (array_merge($order, $rest) as $index => $identifier) {
                $struct = $service->newFieldDefinitionUpdateStruct();
                $struct->position = ($index + 1) * 10;
                $service->updateFieldDefinition($draft, $draft->getFieldDefinition($identifier), $struct);
            }
        });

        return CapabilityResult::ok(sprintf('The fields of "%s" were reordered', $arguments['type']), ['type' => $arguments['type']]);
    }
}
