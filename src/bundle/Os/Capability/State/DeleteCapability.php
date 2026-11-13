<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Os\Capability\State;

use Masilia\Bundle\IbexaAi\Os\Capability\RepositoryCapability;
use Masilia\IbexaAi\Os\CapabilityResult;
use Masilia\IbexaAi\Os\Preview;
use Masilia\IbexaAi\Os\Risk;

final class DeleteCapability extends RepositoryCapability
{
    public function name(): string
    {
        return 'state.delete';
    }

    public function summary(): string
    {
        return 'Delete a state; its contents go to the default state of the group.';
    }

    public function description(): string
    {
        return 'Deletes the state for good. The contents that had it get the default state of the group.';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'required' => ['group', 'state'], 'properties' => ['group' => ['type' => 'string', 'minLength' => 1, 'description' => 'Identifier of the group of states'], 'state' => ['type' => 'string', 'minLength' => 1, 'description' => 'Identifier of the state']]];
    }

    public function risk(): Risk
    {
        return Risk::DESTRUCTIVE;
    }

    public function requires(): array
    {
        return [['state', 'administrate']];
    }

    public function examples(): array
    {
        return [['description' => 'Delete a state', 'arguments' => ['group' => 'review', 'state' => 'approved']]];
    }

    public function validate(array $arguments): ?string
    {
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
        return new Preview('Delete a state', [['Group', (string) $arguments['group']], ['State', (string) $arguments['state']]], ['The state is deleted for good; its contents get the default state. This cannot be undone.']);
    }

    public function execute(array $arguments): CapabilityResult
    {
        $service = $this->repository->getObjectStateService();
        $service->deleteObjectState($service->loadObjectStateByIdentifier($service->loadObjectStateGroupByIdentifier((string) $arguments['group']), (string) $arguments['state']));

        return CapabilityResult::ok(sprintf('State "%s" was deleted', $arguments['state']), []);
    }
}
