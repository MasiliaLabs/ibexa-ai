<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Os\Capability\Localization;

use Masilia\IbexaAi\Os\Capability\RepositoryCapability;
use Masilia\Contracts\IbexaAi\Os\CapabilityResult;
use Masilia\Contracts\IbexaAi\Os\Preview;
use Masilia\Contracts\IbexaAi\Os\Risk;

final class LanguageCreateCapability extends RepositoryCapability
{
    public function name(): string
    {
        return 'localization.language.create';
    }

    public function summary(): string
    {
        return 'Add a language to the installation.';
    }

    public function description(): string
    {
        return 'Creates a language contents can then be translated into. It is enabled unless enabled=false.';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'required' => ['language_code', 'name'], 'properties' => ['language_code' => ['type' => 'string', 'minLength' => 2, 'maxLength' => 10, 'description' => 'Language code, e.g. fre-FR'], 'name' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 100], 'enabled' => ['type' => 'boolean']]];
    }

    public function risk(): Risk
    {
        return Risk::WRITE;
    }

    public function requires(): array
    {
        return [['setup', 'administrate']];
    }

    public function examples(): array
    {
        return [['description' => 'Add Spanish', 'arguments' => ['language_code' => 'spa-ES', 'name' => 'Spanish']]];
    }

    public function preview(array $arguments): Preview
    {
        return new Preview('Add a language', [['Code', (string) $arguments['language_code']], ['Name', (string) $arguments['name']]]);
    }

    public function execute(array $arguments): CapabilityResult
    {
        $service = $this->repository->getContentLanguageService();
        $struct = $service->newLanguageCreateStruct();
        $struct->languageCode = (string) $arguments['language_code'];
        $struct->name = (string) $arguments['name'];
        $struct->enabled = (bool) ($arguments['enabled'] ?? true);
        $language = $service->createLanguage($struct);

        return CapabilityResult::ok(sprintf('Language "%s" added', $language->getName()), ['language_code' => $language->getLanguageCode()]);
    }
}
