<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Os\Capability\State;

use Masilia\IbexaAi\Os\Capability\RepositoryCapability;
use Masilia\Contracts\IbexaAi\Os\CapabilityResult;
use Masilia\Contracts\IbexaAi\Os\Preview;
use Masilia\Contracts\IbexaAi\Os\Risk;

final class UpdateCapability extends RepositoryCapability
{
    public function name(): string
    {
        return 'state.update';
    }

    public function summary(): string
    {
        return 'Rename a state, or change its priority.';
    }

    public function description(): string
    {
        return 'Changes the name (default language) and/or the priority of a state; the state with the lowest priority is the default of the group.';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'required' => ['group', 'state'], 'properties' => ['group' => ['type' => 'string', 'minLength' => 1, 'description' => 'Identifier of the group of states'], 'state' => ['type' => 'string', 'minLength' => 1, 'description' => 'Identifier of the state'], 'name' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 190], 'priority' => ['type' => 'integer', 'minimum' => 0]]];
    }

    public function risk(): Risk
    {
        return Risk::WRITE;
    }

    public function requires(): array
    {
        return [['state', 'administrate']];
    }

    public function examples(): array
    {
        return [['description' => 'Rename a state', 'arguments' => ['group' => 'review', 'state' => 'approved', 'name' => 'Validated']]];
    }

    public function validate(array $arguments): ?string
    {
        if (!isset($arguments['name']) && !isset($arguments['priority'])) {
            return 'Give a name or a priority to change.';
        }
        try {
            $this->repository->getObjectStateService()->loadObjectStateGroupByIdentifier((string) $arguments['group']);
        } catch (\Throwable) {
            return sprintf('There is no group of states "%s": see state.list.', $arguments['group']);
        }
        try {
            $service = $this->repository->getObjectStateService();
            $service->loadObjectStateByIdentifier($service->loadObjectStateGroupByIdentifier((string) $arguments['group']), (string) $arguments['state']);
        } catch (\Throwable) {
            return sprintf('There is no state "%s" in the group "%s": see state.list.', $arguments['state'], $arguments['group']);
        }

        return null;
    }

    public function preview(array $arguments): Preview
    {
        return new Preview('Update a state', array_values(array_filter([['Group', (string) $arguments['group']], ['State', (string) $arguments['state']], isset($arguments['name']) ? ['New name', (string) $arguments['name']] : null, isset($arguments['priority']) ? ['Priority', (string) (int) $arguments['priority']] : null])));
    }

    public function execute(array $arguments): CapabilityResult
    {
        $service = $this->repository->getObjectStateService();
        $state = $service->loadObjectStateByIdentifier($service->loadObjectStateGroupByIdentifier((string) $arguments['group']), (string) $arguments['state']);
        if (isset($arguments['name'])) {
            $lang = $this->defaultLanguage();
            $struct = $service->newObjectStateUpdateStruct();
            $struct->defaultLanguageCode = $lang;
            $struct->names = [$lang => (string) $arguments['name']];
            $state = $service->updateObjectState($state, $struct);
        }
        if (isset($arguments['priority'])) {
            $service->setPriorityOfObjectState($state, (int) $arguments['priority']);
        }

        return CapabilityResult::ok(sprintf('State "%s" updated', $state->identifier), ['state' => $state->identifier]);
    }
}
