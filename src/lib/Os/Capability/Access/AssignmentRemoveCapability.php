<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Os\Capability\Access;

use Masilia\Contracts\IbexaAi\Os\CapabilityResult;
use Masilia\Contracts\IbexaAi\Os\Preview;
use Masilia\Contracts\IbexaAi\Os\Risk;

final class AssignmentRemoveCapability extends AccessCapability
{
    public function name(): string
    {
        return 'access.assignment.remove';
    }

    public function summary(): string
    {
        return 'Take a role away from a user group or user.';
    }

    public function description(): string
    {
        return 'Removes one assignment (see access.role.describe for assignment ids): that user or group loses what the role gave.';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'required' => ['assignment_id'], 'properties' => ['assignment_id' => ['type' => 'integer', 'minimum' => 1]]];
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
        return [['description' => 'Remove an assignment', 'arguments' => ['assignment_id' => 17]]];
    }

    public function validate(array $arguments): ?string
    {
        try {
            $this->roles()->loadRoleAssignment((int) $arguments['assignment_id']);
        } catch (\Throwable) {
            return sprintf('There is no assignment %d: see access.role.describe.', (int) $arguments['assignment_id']);
        }

        return null;
    }

    public function preview(array $arguments): Preview
    {
        return new Preview('Remove a role assignment', [['Assignment', (string) (int) $arguments['assignment_id']]], ['The user or group loses what the role gave at once.', self::NOTE]);
    }

    public function execute(array $arguments): CapabilityResult
    {
        $roles = $this->roles();
        $roles->removeRoleAssignment($roles->loadRoleAssignment((int) $arguments['assignment_id']));

        return CapabilityResult::ok(sprintf('Assignment %d removed', (int) $arguments['assignment_id']), []);
    }
}
