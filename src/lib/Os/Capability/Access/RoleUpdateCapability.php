<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Os\Capability\Access;

use Masilia\Contracts\IbexaAi\Os\CapabilityResult;
use Masilia\Contracts\IbexaAi\Os\Preview;
use Masilia\Contracts\IbexaAi\Os\Risk;

final class RoleUpdateCapability extends AccessCapability
{
    public function name(): string
    {
        return 'access.role.update';
    }

    public function summary(): string
    {
        return 'Rename a role.';
    }

    public function description(): string
    {
        return 'Changes the name of a role. Its policies and assignments stay.';
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
        return [['role', 'update']];
    }

    public function examples(): array
    {
        return [['description' => 'Rename a role', 'arguments' => ['role' => 'Press editor', 'new_name' => 'Newsroom editor']]];
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
        return new Preview('Rename a role', [['Role', (string) $arguments['role']], ['New name', (string) $arguments['new_name']]], [self::NOTE]);
    }

    public function execute(array $arguments): CapabilityResult
    {
        $this->editRole((string) $arguments['role'], function ($draft) use ($arguments): void {
            $struct = $this->roles()->newRoleUpdateStruct();
            $struct->identifier = (string) $arguments['new_name'];
            $this->roles()->updateRoleDraft($draft, $struct);
        });

        return CapabilityResult::ok(sprintf('The role is now "%s"', $arguments['new_name']), ['role' => $arguments['new_name']]);
    }
}
