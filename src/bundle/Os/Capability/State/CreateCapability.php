<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Os\Capability\State;

use Masilia\Bundle\IbexaAi\Os\Capability\RepositoryCapability;
use Masilia\IbexaAi\Os\CapabilityResult;
use Masilia\IbexaAi\Os\Preview;
use Masilia\IbexaAi\Os\Risk;

final class CreateCapability extends RepositoryCapability
{
    public function name(): string
    {
        return 'state.create';
    }

    public function summary(): string
    {
        return 'Create a state in a group.';
    }

    public function description(): string
    {
        return 'Adds a state to a group, named in the installation’s default language. The first state of a group is the one every content has by default.';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'required' => ['group', 'identifier', 'name'], 'properties' => ['group' => ['type' => 'string', 'minLength' => 1, 'description' => 'Identifier of the group of states'], 'identifier' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 50, 'description' => 'Lower case letters, digits and _'], 'name' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 190]]];
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
        return [['description' => 'Add a state', 'arguments' => ['group' => 'review', 'identifier' => 'approved', 'name' => 'Approved']]];
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
        return new Preview('Create a state', [['Group', (string) $arguments['group']], ['Identifier', (string) $arguments['identifier']], ['Name', (string) $arguments['name']]]);
    }

    public function execute(array $arguments): CapabilityResult
    {
        $service = $this->repository->getObjectStateService();
        $lang = $this->defaultLanguage();
        $struct = $service->newObjectStateCreateStruct((string) $arguments['identifier']);
        $struct->defaultLanguageCode = $lang;
        $struct->names = [$lang => (string) $arguments['name']];
        $state = $service->createObjectState($service->loadObjectStateGroupByIdentifier((string) $arguments['group']), $struct);

        return CapabilityResult::ok(sprintf('State "%s" created', $state->identifier), ['state' => $state->identifier]);
    }
}
