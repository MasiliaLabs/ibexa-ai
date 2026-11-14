<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Os\Capability\Access;

use Masilia\Contracts\IbexaAi\Os\CapabilityResult;
use Masilia\Contracts\IbexaAi\Os\Risk;

final class RoleListCapability extends AccessCapability
{
    public function name(): string
    {
        return 'access.role.list';
    }

    public function summary(): string
    {
        return 'List the roles with how many policies and assignments each has.';
    }

    public function description(): string
    {
        return 'Roles hold policies (what can be done) and are assigned to users and groups. Returns role id, name, number of policies and of assignments.';
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
        return [['role', 'read']];
    }

    public function examples(): array
    {
        return [['description' => 'All roles', 'arguments' => []]];
    }

    public function execute(array $arguments): CapabilityResult
    {
        $roles = $this->roles();
        $rows = [];
        foreach ($roles->loadRoles() as $role) {
            $rows[] = ['role_id' => $role->id, 'name' => $role->identifier, 'policies' => count(iterator_to_array($role->getPolicies(), false)), 'assignments' => $roles->countRoleAssignments($role)];
        }

        return CapabilityResult::ok(sprintf('%d role(s)', count($rows)), ['roles' => $rows]);
    }
}
