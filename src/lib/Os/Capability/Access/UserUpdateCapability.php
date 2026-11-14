<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Os\Capability\Access;

use Masilia\Contracts\IbexaAi\Os\CapabilityResult;
use Masilia\Contracts\IbexaAi\Os\Preview;
use Masilia\Contracts\IbexaAi\Os\Risk;

final class UserUpdateCapability extends AccessCapability
{
    public function name(): string
    {
        return 'access.user.update';
    }

    public function summary(): string
    {
        return 'Change the email, name or status of a user.';
    }

    public function description(): string
    {
        return 'Changes what you give: email, first_name, last_name, enabled (false locks the account). Passwords are never handled here.';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'required' => ['user_id'], 'properties' => ['user_id' => ['type' => 'integer', 'minimum' => 1], 'email' => ['type' => 'string', 'minLength' => 3], 'first_name' => ['type' => 'string', 'minLength' => 1], 'last_name' => ['type' => 'string', 'minLength' => 1], 'enabled' => ['type' => 'boolean']]];
    }

    public function risk(): Risk
    {
        return Risk::ACCESS;
    }

    public function requires(): array
    {
        return [['user', 'selfedit']];
    }

    public function examples(): array
    {
        return [['description' => 'Lock an account', 'arguments' => ['user_id' => 15, 'enabled' => false]]];
    }

    public function validate(array $arguments): ?string
    {
        if (!isset($arguments['email']) && !isset($arguments['first_name']) && !isset($arguments['last_name']) && !isset($arguments['enabled'])) {
            return 'Give something to change: email, first_name, last_name or enabled.';
        }
        if (isset($arguments['email']) && filter_var($arguments['email'], FILTER_VALIDATE_EMAIL) === false) {
            return sprintf('"%s" is not an email address.', $arguments['email']);
        }
        try {
            $user = $this->repository->getUserService()->loadUser((int) $arguments['user_id']);
        } catch (\Throwable) {
            return sprintf('There is no user %d.', (int) $arguments['user_id']);
        }

        return ($arguments['enabled'] ?? true) === false && (int) $arguments['user_id'] === (int) $this->repository->getPermissionResolver()->getCurrentUserReference()->getUserId() ? 'You cannot disable your own account.' : null;
    }

    public function preview(array $arguments): Preview
    {
        return new Preview('Update a user', array_values(array_filter([['User', $this->userLabel((int) $arguments['user_id'])], isset($arguments['email']) ? ['Email', (string) $arguments['email']] : null, isset($arguments['first_name']) ? ['First name', (string) $arguments['first_name']] : null, isset($arguments['last_name']) ? ['Last name', (string) $arguments['last_name']] : null, isset($arguments['enabled']) ? ['Enabled', $arguments['enabled'] ? 'yes' : 'no'] : null])), [self::NOTE]);
    }

    public function execute(array $arguments): CapabilityResult
    {
        $users = $this->repository->getUserService();
        $content = $this->repository->getContentService();
        $user = $users->loadUser((int) $arguments['user_id']);
        $struct = $users->newUserUpdateStruct();
        $struct->email = $arguments['email'] ?? null;
        $struct->enabled = isset($arguments['enabled']) ? (bool) $arguments['enabled'] : null;
        if (isset($arguments['first_name']) || isset($arguments['last_name'])) {
            $struct->contentUpdateStruct = $content->newContentUpdateStruct();
            foreach (['first_name', 'last_name'] as $field) {
                if (isset($arguments[$field])) {
                    $struct->contentUpdateStruct->setField($field, (string) $arguments[$field]);
                }
            }
        }
        $users->updateUser($user, $struct);

        return CapabilityResult::ok(sprintf('User "%s" updated', $user->login), ['user_id' => $user->getUserId()]);
    }
}
