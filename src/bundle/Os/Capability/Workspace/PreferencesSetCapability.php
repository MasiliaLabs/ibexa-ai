<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Os\Capability\Workspace;

use Masilia\Bundle\IbexaAi\Os\Capability\RepositoryCapability;
use Masilia\IbexaAi\Os\CapabilityResult;
use Masilia\IbexaAi\Os\Preview;
use Masilia\IbexaAi\Os\Risk;

final class PreferencesSetCapability extends RepositoryCapability
{
    public function name(): string
    {
        return 'workspace.preferences.set';
    }

    public function summary(): string
    {
        return 'Set a preference of the user.';
    }

    public function description(): string
    {
        return 'Sets one preference of the user\'s own account (for example "timezone" or "language"); see workspace.preferences.list for the names in use.';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'required' => ['name', 'value'], 'properties' => ['name' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 100], 'value' => ['type' => 'string', 'maxLength' => 255]]];
    }

    public function risk(): Risk
    {
        return Risk::WRITE;
    }

    public function requires(): array
    {
        return [['user', 'preferences']];
    }

    public function examples(): array
    {
        return [['description' => 'Change the timezone', 'arguments' => ['name' => 'timezone', 'value' => 'Africa/Casablanca']]];
    }

    public function preview(array $arguments): Preview
    {
        return new Preview('Set a preference', [['Preference', (string) $arguments['name']], ['Value', (string) $arguments['value']]]);
    }

    public function execute(array $arguments): CapabilityResult
    {
        $struct = new \Ibexa\Contracts\Core\Repository\Values\UserPreference\UserPreferenceSetStruct(['name' => (string) $arguments['name'], 'value' => (string) $arguments['value']]);
        $this->repository->getUserPreferenceService()->setUserPreference([$struct]);

        return CapabilityResult::ok(sprintf('The preference "%s" is now "%s"', $arguments['name'], $arguments['value']), []);
    }
}
