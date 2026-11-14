<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Os\Capability\Access;

use Masilia\Contracts\IbexaAi\Os\CapabilityResult;
use Masilia\Contracts\IbexaAi\Os\Preview;
use Masilia\Contracts\IbexaAi\Os\Risk;

final class RoleDeleteCapability extends AccessCapability
{
    public function name(): string
    {
        return 'access.role.delete';
    }

    public function summary(): string
    {
        return 'Delete a role, which removes it from everyone it was assigned to.';
    }

    public function description(): string
    {
        return 'Deletes the role and its assignments for good: the users and groups lose what it gave them.';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'required' => ['role'], 'properties' => ['role' => ['type' => 'string', 'minLength' => 1, 'description' => 'Name of the role (see access.role.list)']]];
    }

    public function risk(): Risk
    {
        return Risk::ACCESS;
    }

    public function requires(): array
    {
        return [['role', 'delete']];
    }

    public function examples(): array
    {
        return [['description' => 'Delete a role', 'arguments' => ['role' => 'Press editor']]];
    }

    public function validate(array $arguments): ?string
    {
        if (($problem = $this->missingRole((string) $arguments['role'])) !== null) {
            return $problem;
        }

        return in_array($arguments['role'], ['Administrator', 'Anonymous'], true) ? sprintf('The role "%s" is needed by the system and cannot be deleted.', $arguments['role']) : null;
    }

    public function preview(array $arguments): Preview
    {
        return new Preview('Delete a role', [['Role', (string) $arguments['role']], ['Assigned', (string) $this->roles()->countRoleAssignments($this->roles()->loadRoleByIdentifier((string) $arguments['role']))]], ['The role is deleted for good and removed from everyone it was assigned to. This cannot be undone.', self::NOTE]);
    }

    public function execute(array $arguments): CapabilityResult
    {
        $roles = $this->roles();
        $roles->deleteRole($roles->loadRoleByIdentifier((string) $arguments['role']));

        return CapabilityResult::ok(sprintf('Role "%s" was deleted', $arguments['role']), []);
    }
}
