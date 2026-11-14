<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Os\Capability\State;

use Masilia\Bundle\IbexaAi\Os\Capability\RepositoryCapability;
use Masilia\Contracts\IbexaAi\Os\CapabilityResult;
use Masilia\Contracts\IbexaAi\Os\Preview;
use Masilia\Contracts\IbexaAi\Os\Risk;

final class GroupDeleteCapability extends RepositoryCapability
{
    public function name(): string
    {
        return 'state.group.delete';
    }

    public function summary(): string
    {
        return 'Delete a group of object states, with its states.';
    }

    public function description(): string
    {
        return 'Deletes the group and all its states for good: the contents lose the flags they had in it.';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'required' => ['group'], 'properties' => ['group' => ['type' => 'string', 'minLength' => 1, 'description' => 'Identifier of the group of states']]];
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
        return [['description' => 'Delete a group', 'arguments' => ['group' => 'review']]];
    }

    public function validate(array $arguments): ?string
    {
        try {
            $this->repository->getObjectStateService()->loadObjectStateGroupByIdentifier((string) $arguments['group']);
        } catch (\Throwable) {
            return sprintf('There is no group of states "%s": see state.list.', $arguments['group']);
        }

        return null;
    }

    public function preview(array $arguments): Preview
    {
        return new Preview('Delete a group of states', [['Group', (string) $arguments['group']]], ['The group and its states are deleted for good; contents lose them. This cannot be undone.']);
    }

    public function execute(array $arguments): CapabilityResult
    {
        $service = $this->repository->getObjectStateService();
        $service->deleteObjectStateGroup($service->loadObjectStateGroupByIdentifier((string) $arguments['group']));

        return CapabilityResult::ok(sprintf('Group "%s" was deleted', $arguments['group']), []);
    }
}
