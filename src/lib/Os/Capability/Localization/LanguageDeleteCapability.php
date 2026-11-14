<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Os\Capability\Localization;

use Masilia\IbexaAi\Os\Capability\RepositoryCapability;
use Masilia\Contracts\IbexaAi\Os\CapabilityResult;
use Masilia\Contracts\IbexaAi\Os\Preview;
use Masilia\Contracts\IbexaAi\Os\Risk;

final class LanguageDeleteCapability extends RepositoryCapability
{
    public function name(): string
    {
        return 'localization.language.delete';
    }

    public function summary(): string
    {
        return 'Delete a language and the translations in it.';
    }

    public function description(): string
    {
        return 'Deletes the language for good, with every translation of every content in it. Check the contents first; the default language cannot be deleted.';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'required' => ['language_code'], 'properties' => ['language_code' => ['type' => 'string', 'minLength' => 2, 'maxLength' => 10, 'description' => 'Language code, e.g. fre-FR']]];
    }

    public function risk(): Risk
    {
        return Risk::DESTRUCTIVE;
    }

    public function requires(): array
    {
        return [['setup', 'administrate']];
    }

    public function examples(): array
    {
        return [['description' => 'Delete a language', 'arguments' => ['language_code' => 'spa-ES']]];
    }

    public function validate(array $arguments): ?string
    {
        try {
            $this->repository->getContentLanguageService()->loadLanguage((string) $arguments['language_code']);
        } catch (\Throwable) {
            return sprintf('There is no language "%s": see localization.language.list.', $arguments['language_code']);
        }

        return $arguments['language_code'] === $this->defaultLanguage() ? 'The default language cannot be deleted.' : null;
    }

    public function preview(array $arguments): Preview
    {
        return new Preview('Delete a language', [['Language', (string) $arguments['language_code']]], ['Every translation of every content in this language is deleted for good. This cannot be undone.']);
    }

    public function execute(array $arguments): CapabilityResult
    {
        $service = $this->repository->getContentLanguageService();
        $service->deleteLanguage($service->loadLanguage((string) $arguments['language_code']));

        return CapabilityResult::ok(sprintf('Language "%s" was deleted', $arguments['language_code']), []);
    }
}
