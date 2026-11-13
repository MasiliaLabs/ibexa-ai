<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Os\Capability\Workspace;

use Masilia\Bundle\IbexaAi\Os\Capability\RepositoryCapability;
use Masilia\IbexaAi\Os\CapabilityResult;
use Masilia\IbexaAi\Os\Risk;

final class PreferencesListCapability extends RepositoryCapability
{
    public function name(): string
    {
        return 'workspace.preferences.list';
    }

    public function summary(): string
    {
        return 'List the preferences of the user (language, timezone, ...).';
    }

    public function description(): string
    {
        return 'Returns the name and value of each preference the user has set.';
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
        return [['user', 'preferences']];
    }

    public function examples(): array
    {
        return [['description' => 'My preferences', 'arguments' => []]];
    }

    public function execute(array $arguments): CapabilityResult
    {
        $list = $this->repository->getUserPreferenceService()->loadUserPreferences(0, 100);
        $rows = [];
        foreach ($list->items as $preference) {
            $rows[] = ['name' => $preference->name, 'value' => $preference->value];
        }

        return CapabilityResult::ok(sprintf('%d preference(s)', count($rows)), ['preferences' => $rows]);
    }
}
