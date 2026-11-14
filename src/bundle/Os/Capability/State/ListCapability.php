<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Os\Capability\State;

use Masilia\Bundle\IbexaAi\Os\Capability\RepositoryCapability;
use Masilia\Contracts\IbexaAi\Os\CapabilityResult;
use Masilia\Contracts\IbexaAi\Os\Risk;

final class ListCapability extends RepositoryCapability
{
    public function name(): string
    {
        return 'state.list';
    }

    public function summary(): string
    {
        return 'List the groups of object states and their states.';
    }

    public function description(): string
    {
        return 'Object states are flags on contents (for example "lock": locked / not locked). Returns each group with its states, their priority and how many contents are in each.';
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
        return [['description' => 'All groups and states', 'arguments' => []]];
    }

    public function execute(array $arguments): CapabilityResult
    {
        $service = $this->repository->getObjectStateService();
        $groups = [];
        foreach ($service->loadObjectStateGroups() as $group) {
            $states = [];
            foreach ($service->loadObjectStates($group) as $state) {
                $states[] = ['identifier' => $state->identifier, 'name' => $state->getName(), 'priority' => $state->priority, 'contents' => $service->getContentCount($state)];
            }
            $groups[] = ['identifier' => $group->identifier, 'name' => $group->getName(), 'states' => $states];
        }

        return CapabilityResult::ok(sprintf('%d group(s) of states', count($groups)), ['groups' => $groups]);
    }
}
