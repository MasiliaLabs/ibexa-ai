<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Os\Capability\Localization;

use Masilia\Bundle\IbexaAi\Os\Capability\RepositoryCapability;
use Masilia\IbexaAi\Os\CapabilityResult;
use Masilia\IbexaAi\Os\Risk;

final class LanguageListCapability extends RepositoryCapability
{
    public function name(): string
    {
        return 'localization.language.list';
    }

    public function summary(): string
    {
        return 'List the languages of the installation.';
    }

    public function description(): string
    {
        return 'Returns the languages contents can be translated into: code, name, whether enabled, and which one is the default.';
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
        return [['description' => 'All languages', 'arguments' => []]];
    }

    public function execute(array $arguments): CapabilityResult
    {
        $service = $this->repository->getContentLanguageService();
        $default = $service->getDefaultLanguageCode();
        $rows = [];
        foreach ($service->loadLanguages() as $language) {
            $rows[] = ['language_code' => $language->getLanguageCode(), 'name' => $language->getName(), 'enabled' => $language->isEnabled(), 'default' => $language->getLanguageCode() === $default];
        }

        return CapabilityResult::ok(sprintf('%d language(s)', count($rows)), ['languages' => $rows]);
    }
}
