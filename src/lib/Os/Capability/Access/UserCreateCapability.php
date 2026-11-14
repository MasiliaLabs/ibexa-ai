<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Os\Capability\Access;

use Masilia\Contracts\IbexaAi\Os\CapabilityResult;
use Masilia\Contracts\IbexaAi\Os\Preview;
use Masilia\Contracts\IbexaAi\Os\Risk;

final class UserCreateCapability extends AccessCapability
{
    public function name(): string
    {
        return 'access.user.create';
    }

    public function summary(): string
    {
        return 'Create a user in a user group, with a password nobody knows.';
    }

    public function description(): string
    {
        return 'Creates an enabled user. The password is random and is shown to no one: the person sets theirs with "forgot password" on the login page. Never ask the user for a password and never put one in a message. user_group_id is the content id of the group the user goes in; roles come from the group.';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'required' => ['login', 'email', 'first_name', 'last_name', 'user_group_id'], 'properties' => ['login' => ['type' => 'string', 'minLength' => 3, 'maxLength' => 100], 'email' => ['type' => 'string', 'minLength' => 3, 'maxLength' => 190], 'first_name' => ['type' => 'string', 'minLength' => 1], 'last_name' => ['type' => 'string', 'minLength' => 1], 'user_group_id' => ['type' => 'integer', 'minimum' => 1]]];
    }

    public function risk(): Risk
    {
        return Risk::ACCESS;
    }

    public function requires(): array
    {
        return [['user', 'register']];
    }

    public function examples(): array
    {
        return [['description' => 'A new editor', 'arguments' => ['login' => 'jdoe', 'email' => 'jdoe@example.org', 'first_name' => 'Jane', 'last_name' => 'Doe', 'user_group_id' => 13]]];
    }

    public function validate(array $arguments): ?string
    {
        if (filter_var($arguments['email'], FILTER_VALIDATE_EMAIL) === false) {
            return sprintf('"%s" is not an email address.', $arguments['email']);
        }
        $users = $this->repository->getUserService();
        try {
            $users->loadUserGroup((int) $arguments['user_group_id']);
        } catch (\Throwable) {
            return sprintf('The user group %d does not exist.', (int) $arguments['user_group_id']);
        }
        try {
            $users->loadUserByLogin((string) $arguments['login']);

            return sprintf('The login "%s" is taken.', $arguments['login']);
        } catch (\Throwable) {
            return null;
        }
    }

    public function preview(array $arguments): Preview
    {
        return new Preview('Create a user', [['Login', (string) $arguments['login']], ['Email', (string) $arguments['email']], ['Name', $arguments['first_name'] . ' ' . $arguments['last_name']], ['User group', (string) (int) $arguments['user_group_id']]], ['The password is random: the person sets theirs with "forgot password".', self::NOTE]);
    }

    public function execute(array $arguments): CapabilityResult
    {
        $users = $this->repository->getUserService();
        $struct = $users->newUserCreateStruct((string) $arguments['login'], (string) $arguments['email'], 'Aa1!' . bin2hex(random_bytes(12)), $this->defaultLanguage());
        $struct->setField('first_name', (string) $arguments['first_name']);
        $struct->setField('last_name', (string) $arguments['last_name']);
        $user = $users->createUser($struct, [$users->loadUserGroup((int) $arguments['user_group_id'])]);

        return CapabilityResult::ok(sprintf('User "%s" created; they set their password with "forgot password"', $user->login), ['user_id' => $user->getUserId()]);
    }
}
