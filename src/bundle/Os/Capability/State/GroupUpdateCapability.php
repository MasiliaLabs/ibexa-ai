<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Os\Capability\State;

use Masilia\Bundle\IbexaAi\Os\Capability\RepositoryCapability;
use Masilia\IbexaAi\Os\CapabilityResult;
use Masilia\IbexaAi\Os\Preview;
use Masilia\IbexaAi\Os\Risk;

final class GroupUpdateCapability extends RepositoryCapability
{
    public function name(): string
    {
        return 'state.group.update';
    }

    public function summary(): string
    {
        return 'Rename a group of object states.';
    }

    public function description(): string
    {
        return 'Changes the name of a group, in the installation’s default language.';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'required' => ['group', 'name'], 'properties' => ['group' => ['type' => 'string', 'minLength' => 1, 'description' => 'Identifier of the group of states'], 'name' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 190]]];
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
        return [['description' => 'Rename a group', 'arguments' => ['group' => 'review', 'name' => 'Editorial review']]];
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
        return new Preview('Rename a group of states', [['Group', (string) $arguments['group']], ['New name', (string) $arguments['name']]]);
    }

    public function execute(array $arguments): CapabilityResult
    {
        $service = $this->repository->getObjectStateService();
        $lang = $this->defaultLanguage();
        $struct = $service->newObjectStateGroupUpdateStruct();
        $struct->defaultLanguageCode = $lang;
        $struct->names = [$lang => (string) $arguments['name']];
        $group = $service->updateObjectStateGroup($service->loadObjectStateGroupByIdentifier((string) $arguments['group']), $struct);

        return CapabilityResult::ok(sprintf('Group "%s" renamed', $group->identifier), ['group' => $group->identifier]);
    }
}
