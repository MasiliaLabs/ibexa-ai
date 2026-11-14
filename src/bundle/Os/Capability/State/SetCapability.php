<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Os\Capability\State;

use Masilia\Bundle\IbexaAi\Os\Capability\RepositoryCapability;
use Masilia\Contracts\IbexaAi\Os\CapabilityResult;
use Masilia\Contracts\IbexaAi\Os\Preview;
use Masilia\Contracts\IbexaAi\Os\Risk;

final class SetCapability extends RepositoryCapability
{
    public function name(): string
    {
        return 'state.set';
    }

    public function summary(): string
    {
        return 'Set the state of a content in a group (for example lock it).';
    }

    public function description(): string
    {
        return 'Puts the content in the given state of the given group; it leaves the state it had in that group.';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'required' => ['content_id', 'group', 'state'], 'properties' => ['content_id' => ['type' => 'integer', 'minimum' => 1], 'group' => ['type' => 'string', 'minLength' => 1, 'description' => 'Identifier of the group of states'], 'state' => ['type' => 'string', 'minLength' => 1, 'description' => 'Identifier of the state']]];
    }

    public function risk(): Risk
    {
        return Risk::WRITE;
    }

    public function requires(): array
    {
        return [['state', 'assign']];
    }

    public function examples(): array
    {
        return [['description' => 'Lock a content', 'arguments' => ['content_id' => 1353, 'group' => 'ez_lock', 'state' => 'locked']]];
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
        return new Preview('Set the state of a content', [['Content', (string) (int) $arguments['content_id']], ['Group', (string) $arguments['group']], ['State', (string) $arguments['state']]]);
    }

    public function execute(array $arguments): CapabilityResult
    {
        $service = $this->repository->getObjectStateService();
        $group = $service->loadObjectStateGroupByIdentifier((string) $arguments['group']);
        $info = $this->repository->getContentService()->loadContentInfo((int) $arguments['content_id']);
        $service->setContentState($info, $group, $service->loadObjectStateByIdentifier($group, (string) $arguments['state']));

        return CapabilityResult::ok(sprintf('"%s" is now "%s" in "%s"', $info->name, $arguments['state'], $arguments['group']), ['content_id' => $info->id]);
    }
}
