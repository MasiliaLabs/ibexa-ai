<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Os\Capability\Structure;

use Masilia\Contracts\IbexaAi\Os\CapabilityResult;
use Masilia\Contracts\IbexaAi\Os\Preview;
use Masilia\Contracts\IbexaAi\Os\Risk;

final class TypeTranslateCapability extends TypeCapability
{
    public function name(): string
    {
        return 'structure.type.translate';
    }

    public function summary(): string
    {
        return 'Name a content type in another language.';
    }

    public function description(): string
    {
        return 'Sets the label of the content type in a language, as shown in the admin interface in that language.';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'required' => ['type', 'language_code', 'name'], 'properties' => ['type' => ['type' => 'string', 'minLength' => 1, 'description' => 'Identifier of the content type'], 'language_code' => ['type' => 'string', 'minLength' => 2, 'maxLength' => 10], 'name' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 190]]];
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
        return [['description' => 'French label of a type', 'arguments' => ['type' => 'landing_page', 'language_code' => 'fre-FR', 'name' => 'Page de campagne']]];
    }

    public function validate(array $arguments): ?string
    {
        return $this->missingType((string) $arguments['type']);
    }

    public function preview(array $arguments): Preview
    {
        return new Preview('Translate a content type name', [['Type', (string) $arguments['type']], ['Language', (string) $arguments['language_code']], ['Name', (string) $arguments['name']]]);
    }

    public function execute(array $arguments): CapabilityResult
    {
        $service = $this->types();
        $this->edit((string) $arguments['type'], static function ($draft, $type) use ($service, $arguments): void {
            $struct = $service->newContentTypeUpdateStruct();
            $struct->names = array_merge($type->getNames(), [(string) $arguments['language_code'] => (string) $arguments['name']]);
            $service->updateContentTypeDraft($draft, $struct);
        });

        return CapabilityResult::ok(sprintf('"%s" is named "%s" in %s', $arguments['type'], $arguments['name'], $arguments['language_code']), ['type' => $arguments['type']]);
    }
}
