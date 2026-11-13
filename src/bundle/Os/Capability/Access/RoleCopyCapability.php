<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Os\Capability\Access;

use Masilia\IbexaAi\Os\CapabilityResult;
use Masilia\IbexaAi\Os\Preview;
use Masilia\IbexaAi\Os\Risk;

final class RoleCopyCapability extends AccessCapability
{
    public function name(): string
    {
        return 'access.role.copy';
    }

    public function summary(): string
    {
        return 'Copy a role with its policies (not its assignments).';
    }

    public function description(): string
    {
        return 'Creates a new role with the same policies. Assignments are not copied.';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'required' => ['role', 'new_name'], 'properties' => ['role' => ['type' => 'string', 'minLength' => 1, 'description' => 'Name of the role (see access.role.list)'], 'new_name' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 100]]];
    }

    public function risk(): Risk
    {
        return Risk::ACCESS;
    }

    public function requires(): array
    {
        return [['role', 'create']];
    }

    public function examples(): array
    {
        return [['description' => 'Start from an existing role', 'arguments' => ['role' => 'Editor', 'new_name' => 'Editor (copy)']]];
    }

    public function validate(array $arguments): ?string
    {
        if (($problem = $this->missingRole((string) $arguments['role'])) !== null) {
            return $problem;
        }

        return $this->missingRole((string) $arguments['new_name']) === null ? sprintf('There is already a role "%s".', $arguments['new_name']) : null;
    }

    public function preview(array $arguments): Preview
    {
        return new Preview('Copy a role', [['Role', (string) $arguments['role']], ['Copy named', (string) $arguments['new_name']]], [self::NOTE]);
    }

    public function execute(array $arguments): CapabilityResult
    {
        $roles = $this->roles();
        $copy = $roles->copyRole($roles->loadRoleByIdentifier((string) $arguments['role']), $roles->newRoleCopyStruct((string) $arguments['new_name']));

        return CapabilityResult::ok(sprintf('Copied to "%s"', $copy->identifier), ['role' => $copy->identifier]);
    }
}
