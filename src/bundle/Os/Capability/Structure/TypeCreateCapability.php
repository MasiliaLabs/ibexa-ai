<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Os\Capability\Structure;

use Masilia\IbexaAi\Os\CapabilityResult;
use Masilia\IbexaAi\Os\Preview;
use Masilia\IbexaAi\Os\Risk;

final class TypeCreateCapability extends TypeCapability
{
    public function name(): string
    {
        return 'structure.type.create';
    }

    public function summary(): string
    {
        return 'Create a content type with its fields.';
    }

    public function description(): string
    {
        return 'Creates and publishes a content type in a group. fields is the list of fields in order: {identifier, field_type, name, required?, translatable?, searchable?, settings?}. name_schema (what names the contents, e.g. "<title>") defaults to the first text field; url_alias_schema defaults to it. container=true lets contents of the type have children.';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'required' => ['identifier', 'name', 'group', 'fields'], 'properties' => ['identifier' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 50, 'description' => 'Lower case letters, digits and _'], 'name' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 190], 'group' => ['type' => 'string', 'minLength' => 1, 'description' => 'Identifier of the group of content types'], 'container' => ['type' => 'boolean'], 'name_schema' => ['type' => 'string'], 'url_alias_schema' => ['type' => 'string'], 'fields' => ['type' => 'array', 'minItems' => 1, 'items' => ['type' => 'object', 'required' => ['identifier', 'field_type'], 'properties' => ['identifier' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 50, 'description' => 'Lower case letters, digits and _'], 'field_type' => ['type' => 'string', 'description' => 'e.g. ibexa_string, ibexa_text, ibexa_richtext, ibexa_integer, ibexa_boolean, ibexa_image, ibexa_object_relation_list (see structure.field_type.list)'], 'name' => ['type' => 'string', 'description' => 'Label in the default language'], 'required' => ['type' => 'boolean'], 'translatable' => ['type' => 'boolean'], 'searchable' => ['type' => 'boolean'], 'settings' => ['type' => 'object', 'description' => 'Settings of the field type, when it has some (e.g. the options of a selection)']]]]]];
    }

    public function risk(): Risk
    {
        return Risk::WRITE;
    }

    public function requires(): array
    {
        return [['class', 'create']];
    }

    public function examples(): array
    {
        return [['description' => 'A landing page type', 'arguments' => ['identifier' => 'landing_page', 'name' => 'Landing page', 'group' => 'Content', 'container' => true, 'fields' => [['identifier' => 'title', 'field_type' => 'ibexa_string', 'name' => 'Title', 'required' => true], ['identifier' => 'body', 'field_type' => 'ibexa_richtext', 'name' => 'Body']]]]];
    }

    public function validate(array $arguments): ?string
    {
        if ($this->missingType((string) $arguments['identifier']) === null) {
            return sprintf('There is already a content type "%s".', $arguments['identifier']);
        }
        if (($problem = $this->missingGroup((string) $arguments['group'])) !== null) {
            return $problem;
        }
        $taken = [];
        foreach ((array) $arguments['fields'] as $field) {
            if (($problem = $this->fieldProblem((array) $field, $taken)) !== null) {
                return $problem;
            }
            $taken[] = (string) $field['identifier'];
        }

        return null;
    }

    public function preview(array $arguments): Preview
    {
        return new Preview('Create a content type', [['Identifier', (string) $arguments['identifier']], ['Name', (string) $arguments['name']], ['Group', (string) $arguments['group']], ['Container', ($arguments['container'] ?? false) ? 'yes' : 'no'], ['Fields', implode(', ', array_map(static fn ($f): string => $f['identifier'] . ':' . $f['field_type'] . (($f['required'] ?? false) ? '*' : ''), (array) $arguments['fields']))]], ['* = required']);
    }

    public function execute(array $arguments): CapabilityResult
    {
        $service = $this->types();
        $lang = $this->defaultLanguage();
        $fields = (array) $arguments['fields'];
        $nameField = (string) $fields[0]['identifier'];
        foreach ($fields as $field) {
            if ($field['field_type'] === 'ibexa_string') {
                $nameField = (string) $field['identifier'];
                break;
            }
        }
        $struct = $service->newContentTypeCreateStruct((string) $arguments['identifier']);
        $struct->mainLanguageCode = $lang;
        $struct->names = [$lang => (string) $arguments['name']];
        $struct->nameSchema = (string) ($arguments['name_schema'] ?? '<' . $nameField . '>');
        $struct->urlAliasSchema = (string) ($arguments['url_alias_schema'] ?? $struct->nameSchema);
        $struct->isContainer = (bool) ($arguments['container'] ?? false);
        foreach ($fields as $position => $field) {
            $definition = $this->fieldStruct((array) $field);
            $definition->position = ($position + 1) * 10;
            $struct->addFieldDefinition($definition);
        }

        $this->repository->beginTransaction();
        try {
            $draft = $service->createContentType($struct, [$service->loadContentTypeGroupByIdentifier((string) $arguments['group'])]);
            $service->publishContentTypeDraft($draft);
            $this->repository->commit();
        } catch (\Throwable $e) {
            $this->repository->rollback();
            throw $e;
        }

        return CapabilityResult::ok(sprintf('Content type "%s" created with %d field(s)', $arguments['identifier'], count($fields)), ['type' => $arguments['identifier']]);
    }
}
