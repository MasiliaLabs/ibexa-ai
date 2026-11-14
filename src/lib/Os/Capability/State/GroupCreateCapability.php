<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Os\Capability\State;

use Masilia\IbexaAi\Os\Capability\RepositoryCapability;
use Masilia\Contracts\IbexaAi\Os\CapabilityResult;
use Masilia\Contracts\IbexaAi\Os\Preview;
use Masilia\Contracts\IbexaAi\Os\Risk;

final class GroupCreateCapability extends RepositoryCapability
{
    public function name(): string
    {
        return 'state.group.create';
    }

    public function summary(): string
    {
        return 'Create a group of object states.';
    }

    public function description(): string
    {
        return 'Creates an empty group, named in the installation’s default language. Add its states with state.create.';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'required' => ['identifier', 'name'], 'properties' => ['identifier' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 50, 'description' => 'Lower case letters, digits and _'], 'name' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 190]]];
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
        return [['description' => 'A group for the review workflow', 'arguments' => ['identifier' => 'review', 'name' => 'Review']]];
    }

    public function preview(array $arguments): Preview
    {
        return new Preview('Create a group of states', [['Identifier', (string) $arguments['identifier']], ['Name', (string) $arguments['name']]]);
    }

    public function execute(array $arguments): CapabilityResult
    {
        $service = $this->repository->getObjectStateService();
        $lang = $this->defaultLanguage();
        $struct = $service->newObjectStateGroupCreateStruct((string) $arguments['identifier']);
        $struct->defaultLanguageCode = $lang;
        $struct->names = [$lang => (string) $arguments['name']];
        $group = $service->createObjectStateGroup($struct);

        return CapabilityResult::ok(sprintf('Group "%s" created', $group->identifier), ['group' => $group->identifier]);
    }
}
