<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Os\Capability\Access;

use Masilia\IbexaAi\Os\CapabilityResult;
use Masilia\IbexaAi\Os\Risk;

final class UserDescribeCapability extends AccessCapability
{
    public function name(): string
    {
        return 'access.user.describe';
    }

    public function summary(): string
    {
        return 'Describe a user: email, status, groups and roles.';
    }

    public function description(): string
    {
        return 'Find a user by user_id or login. Returns login, email, whether enabled, the user groups and the roles that apply (also through groups).';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'required' => [], 'properties' => ['user_id' => ['type' => 'integer', 'minimum' => 1], 'login' => ['type' => 'string', 'minLength' => 1]]];
    }

    public function risk(): Risk
    {
        return Risk::READ;
    }

    public function requires(): array
    {
        return [['user', 'login']];
    }

    public function examples(): array
    {
        return [['description' => 'Who can this person act as', 'arguments' => ['login' => 'admin']]];
    }

    public function validate(array $arguments): ?string
    {
        if (isset($arguments['user_id']) === isset($arguments['login'])) {
            return 'Give either user_id or login.';
        }
        try {
            isset($arguments['login']) ? $this->repository->getUserService()->loadUserByLogin((string) $arguments['login']) : $this->repository->getUserService()->loadUser((int) $arguments['user_id']);
        } catch (\Throwable) {
            return 'There is no such user.';
        }

        return null;
    }

    public function execute(array $arguments): CapabilityResult
    {
        $users = $this->repository->getUserService();
        $user = isset($arguments['login']) ? $users->loadUserByLogin((string) $arguments['login']) : $users->loadUser((int) $arguments['user_id']);
        $groups = [];
        foreach ($users->loadUserGroupsOfUser($user, 0, 50) as $group) {
            $groups[] = ['user_group_id' => $group->id, 'name' => $group->getName()];
        }
        $roles = [];
        foreach ($this->roles()->getRoleAssignmentsForUser($user, true) as $assignment) {
            $roles[] = $assignment->getRole()->identifier;
        }

        return CapabilityResult::ok(sprintf('User "%s"', $user->login), ['user_id' => $user->getUserId(), 'login' => $user->login, 'email' => $user->email, 'enabled' => $user->enabled, 'groups' => $groups, 'roles' => array_values(array_unique($roles))]);
    }
}
