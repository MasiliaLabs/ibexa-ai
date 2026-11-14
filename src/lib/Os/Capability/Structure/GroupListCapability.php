<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Os\Capability\Structure;

use Masilia\Contracts\IbexaAi\Os\CapabilityResult;
use Masilia\Contracts\IbexaAi\Os\Risk;

final class GroupListCapability extends TypeCapability
{
    public function name(): string
    {
        return 'structure.group.list';
    }

    public function summary(): string
    {
        return 'List the groups of content types.';
    }

    public function description(): string
    {
        return 'Content types are organised in groups (Content, Users, Media...). Returns each group with its content types.';
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
        return [['description' => 'All groups', 'arguments' => []]];
    }

    public function execute(array $arguments): CapabilityResult
    {
        $service = $this->types();
        $rows = [];
        foreach ($service->loadContentTypeGroups() as $group) {
            $types = [];
            foreach ($service->loadContentTypes($group) as $type) {
                $types[] = $type->identifier;
            }
            $rows[] = ['identifier' => $group->identifier, 'group_id' => $group->id, 'types' => $types];
        }

        return CapabilityResult::ok(sprintf('%d group(s)', count($rows)), ['groups' => $rows]);
    }
}
