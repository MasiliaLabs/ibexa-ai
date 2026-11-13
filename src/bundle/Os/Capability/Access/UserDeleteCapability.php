<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Os\Capability\Access;

use Masilia\IbexaAi\Os\CapabilityResult;
use Masilia\IbexaAi\Os\Preview;
use Masilia\IbexaAi\Os\Risk;

final class UserDeleteCapability extends AccessCapability
{
    public function name(): string
    {
        return 'access.user.delete';
    }

    public function summary(): string
    {
        return 'Delete a user account for good.';
    }

    public function description(): string
    {
        return 'Deletes the user and their content record for good (not through the trash). You cannot delete your own account or the Anonymous user.';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'required' => ['user_id'], 'properties' => ['user_id' => ['type' => 'integer', 'minimum' => 1]]];
    }

    public function risk(): Risk
    {
        return Risk::ACCESS;
    }

    public function requires(): array
    {
        return [['content', 'remove']];
    }

    public function examples(): array
    {
        return [['description' => 'Delete an account', 'arguments' => ['user_id' => 15]]];
    }

    public function validate(array $arguments): ?string
    {
        try {
            $user = $this->repository->getUserService()->loadUser((int) $arguments['user_id']);
        } catch (\Throwable) {
            return sprintf('There is no user %d.', (int) $arguments['user_id']);
        }

        if ((int) $arguments['user_id'] === (int) $this->repository->getPermissionResolver()->getCurrentUserReference()->getUserId()) {
            return 'You cannot delete your own account.';
        }

        return $user->login === 'anonymous' ? 'The Anonymous user is needed by the system.' : null;
    }

    public function preview(array $arguments): Preview
    {
        return new Preview('Delete a user', [['User', $this->userLabel((int) $arguments['user_id'])]], ['The account is deleted for good and cannot be restored.', self::NOTE]);
    }

    public function execute(array $arguments): CapabilityResult
    {
        $users = $this->repository->getUserService();
        $user = $users->loadUser((int) $arguments['user_id']);
        $users->deleteUser($user);

        return CapabilityResult::ok(sprintf('User "%s" was deleted', $user->login), []);
    }
}
