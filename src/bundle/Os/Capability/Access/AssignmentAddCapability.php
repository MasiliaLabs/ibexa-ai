<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Os\Capability\Access;

use Masilia\Contracts\IbexaAi\Os\CapabilityResult;
use Masilia\Contracts\IbexaAi\Os\Preview;
use Masilia\Contracts\IbexaAi\Os\Risk;

final class AssignmentAddCapability extends AccessCapability
{
    public function name(): string
    {
        return 'access.assignment.add';
    }

    public function summary(): string
    {
        return 'Give a role to a user group or a user.';
    }

    public function description(): string
    {
        return 'Assigns the role to the user group (user_group_id, the content id of the group) or to a user (user_id). limitation restricts the role to a section or subtree for this assignment.';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'required' => ['role'], 'properties' => ['role' => ['type' => 'string', 'minLength' => 1, 'description' => 'Name of the role (see access.role.list)'], 'user_group_id' => ['type' => 'integer', 'minimum' => 1], 'user_id' => ['type' => 'integer', 'minimum' => 1], 'limitation' => ['type' => 'object', 'required' => ['type', 'values'], 'properties' => ['type' => ['type' => 'string', 'enum' => ['Section', 'Subtree']], 'values' => ['type' => 'array', 'minItems' => 1, 'items' => ['type' => 'string']]], 'description' => 'Limits the role to a section (section ids) or a subtree (paths like "/1/2/")']]];
    }

    public function risk(): Risk
    {
        return Risk::ACCESS;
    }

    public function requires(): array
    {
        return [['role', 'assign']];
    }

    public function examples(): array
    {
        return [['description' => 'Give the press role to a group', 'arguments' => ['role' => 'Press editor', 'user_group_id' => 13]]];
    }

    public function validate(array $arguments): ?string
    {
        if (($problem = $this->missingRole((string) $arguments['role'])) !== null) {
            return $problem;
        }
        if (isset($arguments['user_group_id']) === isset($arguments['user_id'])) {
            return 'Give either user_group_id or user_id, not both and not neither.';
        }
        try {
            isset($arguments['user_id']) ? $this->repository->getUserService()->loadUser((int) $arguments['user_id']) : $this->repository->getUserService()->loadUserGroup((int) $arguments['user_group_id']);
        } catch (\Throwable) {
            return 'That user or user group does not exist.';
        }
        if (isset($arguments['limitation'])) {
            try {
                $this->roles()->getLimitationType((string) $arguments['limitation']['type']);
            } catch (\Throwable) {
                return sprintf('There is no limitation "%s".', $arguments['limitation']['type']);
            }
        }

        return null;
    }

    public function preview(array $arguments): Preview
    {
        return new Preview('Assign a role', [['Role', (string) $arguments['role']], isset($arguments['user_id']) ? ['User', $this->userLabel((int) $arguments['user_id'])] : ['User group', (string) (int) $arguments['user_group_id']], ['Limited to', isset($arguments['limitation']) ? $arguments['limitation']['type'] . ': ' . implode(', ', (array) $arguments['limitation']['values']) : 'nothing']], [self::NOTE]);
    }

    public function execute(array $arguments): CapabilityResult
    {
        $roles = $this->roles();
        $role = $roles->loadRoleByIdentifier((string) $arguments['role']);
        $limitation = isset($arguments['limitation']) ? $roles->getLimitationType((string) $arguments['limitation']['type'])->buildValue(array_values((array) $arguments['limitation']['values'])) : null;
        if (isset($arguments['user_id'])) {
            $roles->assignRoleToUser($role, $this->repository->getUserService()->loadUser((int) $arguments['user_id']), $limitation);
        } else {
            $roles->assignRoleToUserGroup($role, $this->repository->getUserService()->loadUserGroup((int) $arguments['user_group_id']), $limitation);
        }
        $latest = 0;
        foreach ($roles->getRoleAssignments($role) as $assignment) {
            $latest = max($latest, $assignment->getId());
        }

        return CapabilityResult::ok(sprintf('The role "%s" was assigned', $arguments['role']), ['assignment_id' => $latest]);
    }
}
