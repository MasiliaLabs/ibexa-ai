<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Os\Capability\Access;

use Masilia\Contracts\IbexaAi\Os\CapabilityResult;
use Masilia\Contracts\IbexaAi\Os\Risk;

final class RoleDescribeCapability extends AccessCapability
{
    public function name(): string
    {
        return 'access.role.describe';
    }

    public function summary(): string
    {
        return 'Describe a role: its policies with their limitations, and who it is assigned to.';
    }

    public function description(): string
    {
        return 'Returns each policy (policy_id, module, function, limitations) and each assignment (assignment_id, the user group or user, and the limitation of the assignment if any).';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'required' => ['role'], 'properties' => ['role' => ['type' => 'string', 'minLength' => 1, 'description' => 'Name of the role (see access.role.list)']]];
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
        return [['description' => 'What an editor can do', 'arguments' => ['role' => 'Editor']]];
    }

    public function validate(array $arguments): ?string
    {
        return $this->missingRole((string) $arguments['role']);
    }

    public function execute(array $arguments): CapabilityResult
    {
        $roles = $this->roles();
        $role = $roles->loadRoleByIdentifier((string) $arguments['role']);
        $assignments = [];
        foreach ($roles->getRoleAssignments($role) as $assignment) {
            $limitation = $assignment->getRoleLimitation();
            $who = $assignment instanceof \Ibexa\Contracts\Core\Repository\Values\User\UserGroupRoleAssignment ? ['user_group' => $assignment->getUserGroup()->getName(), 'user_group_id' => $assignment->getUserGroup()->id] : ['user' => $assignment->getUser()->login, 'user_id' => $assignment->getUser()->getUserId()];
            $assignments[] = ['assignment_id' => $assignment->getId()] + $who + ['limitation' => $limitation === null ? null : [$limitation->getIdentifier() => array_values($limitation->limitationValues)]];
        }

        return CapabilityResult::ok(sprintf('Role "%s"', $role->identifier), ['name' => $role->identifier, 'policies' => array_map(self::describePolicy(...), iterator_to_array($role->getPolicies(), false)), 'assignments' => $assignments]);
    }
}
