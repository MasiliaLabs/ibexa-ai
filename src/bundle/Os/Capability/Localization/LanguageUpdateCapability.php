<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Os\Capability\Localization;

use Masilia\Bundle\IbexaAi\Os\Capability\RepositoryCapability;
use Masilia\Contracts\IbexaAi\Os\CapabilityResult;
use Masilia\Contracts\IbexaAi\Os\Preview;
use Masilia\Contracts\IbexaAi\Os\Risk;

final class LanguageUpdateCapability extends RepositoryCapability
{
    public function name(): string
    {
        return 'localization.language.update';
    }

    public function summary(): string
    {
        return 'Rename a language, or enable or disable it.';
    }

    public function description(): string
    {
        return 'Changes the name and/or whether the language is enabled. A disabled language stays on existing contents but is not offered for new translations.';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'required' => ['language_code'], 'properties' => ['language_code' => ['type' => 'string', 'minLength' => 2, 'maxLength' => 10, 'description' => 'Language code, e.g. fre-FR'], 'name' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 100], 'enabled' => ['type' => 'boolean']]];
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
        return [['description' => 'Disable a language', 'arguments' => ['language_code' => 'spa-ES', 'enabled' => false]]];
    }

    public function validate(array $arguments): ?string
    {
        if (!isset($arguments['name']) && !isset($arguments['enabled'])) {
            return 'Give a name or enabled to change.';
        }
        try {
            $this->repository->getContentLanguageService()->loadLanguage((string) $arguments['language_code']);
        } catch (\Throwable) {
            return sprintf('There is no language "%s": see localization.language.list.', $arguments['language_code']);
        }

        return null;
    }

    public function preview(array $arguments): Preview
    {
        return new Preview('Update a language', array_values(array_filter([['Language', (string) $arguments['language_code']], isset($arguments['name']) ? ['Name', (string) $arguments['name']] : null, isset($arguments['enabled']) ? ['Enabled', $arguments['enabled'] ? 'yes' : 'no'] : null])));
    }

    public function execute(array $arguments): CapabilityResult
    {
        $service = $this->repository->getContentLanguageService();
        $language = $service->loadLanguage((string) $arguments['language_code']);
        if (isset($arguments['name'])) {
            $language = $service->updateLanguageName($language, (string) $arguments['name']);
        }
        if (isset($arguments['enabled']) && (bool) $arguments['enabled'] !== $language->isEnabled()) {
            $language = $arguments['enabled'] ? $service->enableLanguage($language) : $service->disableLanguage($language);
        }

        return CapabilityResult::ok(sprintf('Language "%s" updated', $language->getName()), ['language_code' => $language->getLanguageCode()]);
    }
}
