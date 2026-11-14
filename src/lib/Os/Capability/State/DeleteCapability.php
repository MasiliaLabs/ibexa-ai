<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Os\Capability\State;

use Masilia\IbexaAi\Os\Capability\RepositoryCapability;
use Masilia\Contracts\IbexaAi\Os\CapabilityResult;
use Masilia\Contracts\IbexaAi\Os\Preview;
use Masilia\Contracts\IbexaAi\Os\Risk;

final class DeleteCapability extends RepositoryCapability
{
    public function name(): string
    {
        return 'state.delete';
    }

    public function summary(): string
    {
        return 'Delete a state that no content is in.';
    }

    public function description(): string
    {
        return 'Deletes the state for good. Only a state that no content is in can be deleted (as in the admin): set its contents to another state first.';
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
        $service = $this->repository->getObjectStateService();
        try {
            $state = $service->loadObjectStateByIdentifier($service->loadObjectStateGroupByIdentifier((string) $arguments['group']), (string) $arguments['state']);
        } catch (\Throwable) {
            return sprintf('There is no state "%s" in the group "%s": see state.list.', $arguments['state'], $arguments['group']);
        }
        // The admin only offers to delete a state that no content is in.
        $used = $service->getContentCount($state);

        return $used > 0 ? sprintf('The state "%s" still holds %d content(s): set them to another state first (state.set).', $arguments['state'], $used) : null;
    }

    public function preview(array $arguments): Preview
    {
        return new Preview('Delete a state', [['Group', (string) $arguments['group']], ['State', (string) $arguments['state']]], ['The state is deleted for good. This cannot be undone.']);
    }

    public function execute(array $arguments): CapabilityResult
    {
        $service = $this->repository->getObjectStateService();
        $service->deleteObjectState($service->loadObjectStateByIdentifier($service->loadObjectStateGroupByIdentifier((string) $arguments['group']), (string) $arguments['state']));

        return CapabilityResult::ok(sprintf('State "%s" was deleted', $arguments['state']), []);
    }
}
