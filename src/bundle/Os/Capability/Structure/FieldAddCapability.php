<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Os\Capability\Structure;

use Masilia\Contracts\IbexaAi\Os\CapabilityResult;
use Masilia\Contracts\IbexaAi\Os\Preview;
use Masilia\Contracts\IbexaAi\Os\Risk;

final class FieldAddCapability extends TypeCapability
{
    public function name(): string
    {
        return 'structure.field.add';
    }

    public function summary(): string
    {
        return 'Add a field to a content type.';
    }

    public function description(): string
    {
        return 'Adds the field at the end of the type; existing contents get an empty value. field is {identifier, field_type, name, required?, translatable?, searchable?, settings?}. A required field on a type that has contents makes those contents invalid until they are edited: add it as not required first.';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'required' => ['type', 'field'], 'properties' => ['type' => ['type' => 'string', 'minLength' => 1, 'description' => 'Identifier of the content type'], 'field' => ['type' => 'object', 'required' => ['identifier', 'field_type'], 'properties' => ['identifier' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 50, 'description' => 'Lower case letters, digits and _'], 'field_type' => ['type' => 'string', 'description' => 'e.g. ibexa_string, ibexa_text, ibexa_richtext, ibexa_integer, ibexa_boolean, ibexa_image, ibexa_object_relation_list (see structure.field_type.list)'], 'name' => ['type' => 'string', 'description' => 'Label in the default language'], 'required' => ['type' => 'boolean'], 'translatable' => ['type' => 'boolean'], 'searchable' => ['type' => 'boolean'], 'settings' => ['type' => 'object', 'description' => 'Settings of the field type, when it has some (e.g. the options of a selection)']]]]];
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
        return [['description' => 'Add a summary field', 'arguments' => ['type' => 'landing_page', 'field' => ['identifier' => 'summary', 'field_type' => 'ibexa_text', 'name' => 'Summary']]]];
    }

    public function validate(array $arguments): ?string
    {
        if (($problem = $this->missingType((string) $arguments['type'])) !== null) {
            return $problem;
        }
        $type = $this->types()->loadContentTypeByIdentifier((string) $arguments['type']);

        return $this->fieldProblem((array) $arguments['field'], array_map(static fn ($f): string => $f->identifier, iterator_to_array($type->getFieldDefinitions(), false)));
    }

    public function preview(array $arguments): Preview
    {
        return new Preview('Add a field', [['Type', (string) $arguments['type']], ['Field', (string) $arguments['field']['identifier']], ['Field type', (string) $arguments['field']['field_type']], ['Required', ($arguments['field']['required'] ?? false) ? 'yes' : 'no']], $this->usedNote((string) $arguments['type']));
    }

    public function execute(array $arguments): CapabilityResult
    {
        $service = $this->types();
        $struct = $this->fieldStruct((array) $arguments['field']);
        $this->edit((string) $arguments['type'], static function ($draft) use ($service, $struct): void {
            $struct->position = (count($draft->getFieldDefinitions()) + 1) * 10;
            $service->addFieldDefinition($draft, $struct);
        });

        return CapabilityResult::ok(sprintf('Field "%s" added to "%s"', $arguments['field']['identifier'], $arguments['type']), ['type' => $arguments['type'], 'field' => $arguments['field']['identifier']]);
    }
}
