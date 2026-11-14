<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Os\Capability\Structure;

use Masilia\Contracts\IbexaAi\Os\CapabilityResult;
use Masilia\Contracts\IbexaAi\Os\Preview;
use Masilia\Contracts\IbexaAi\Os\Risk;

final class TypeUpdateCapability extends TypeCapability
{
    public function name(): string
    {
        return 'structure.type.update';
    }

    public function summary(): string
    {
        return 'Change the name, schemas or container flag of a content type.';
    }

    public function description(): string
    {
        return 'Changes what you give: name (default language), name_schema, url_alias_schema, container. Fields are changed with structure.field.*.';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'required' => ['type'], 'properties' => ['type' => ['type' => 'string', 'minLength' => 1, 'description' => 'Identifier of the content type'], 'name' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 190], 'name_schema' => ['type' => 'string'], 'url_alias_schema' => ['type' => 'string'], 'container' => ['type' => 'boolean']]];
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
        return [['description' => 'Rename a type', 'arguments' => ['type' => 'landing_page', 'name' => 'Campaign page']]];
    }

    public function validate(array $arguments): ?string
    {
        if (!isset($arguments['name']) && !isset($arguments['name_schema']) && !isset($arguments['url_alias_schema']) && !isset($arguments['container'])) {
            return 'Give something to change: name, name_schema, url_alias_schema or container.';
        }

        return $this->missingType((string) $arguments['type']);
    }

    public function preview(array $arguments): Preview
    {
        return new Preview('Update a content type', array_values(array_filter([['Type', (string) $arguments['type']], isset($arguments['name']) ? ['Name', (string) $arguments['name']] : null, isset($arguments['name_schema']) ? ['Name schema', (string) $arguments['name_schema']] : null, isset($arguments['url_alias_schema']) ? ['URL alias schema', (string) $arguments['url_alias_schema']] : null, isset($arguments['container']) ? ['Container', $arguments['container'] ? 'yes' : 'no'] : null])), $this->usedNote((string) $arguments['type']));
    }

    public function execute(array $arguments): CapabilityResult
    {
        $service = $this->types();
        $lang = $this->defaultLanguage();
        $this->edit((string) $arguments['type'], static function ($draft) use ($service, $arguments, $lang): void {
            $struct = $service->newContentTypeUpdateStruct();
            if (isset($arguments['name'])) {
                $struct->names = [$lang => (string) $arguments['name']];
            }
            $struct->nameSchema = $arguments['name_schema'] ?? null;
            $struct->urlAliasSchema = $arguments['url_alias_schema'] ?? null;
            $struct->isContainer = isset($arguments['container']) ? (bool) $arguments['container'] : null;
            $service->updateContentTypeDraft($draft, $struct);
        });

        return CapabilityResult::ok(sprintf('Content type "%s" updated', $arguments['type']), ['type' => $arguments['type']]);
    }
}
