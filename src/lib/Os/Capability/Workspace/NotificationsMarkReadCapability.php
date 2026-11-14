<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Os\Capability\Workspace;

use Masilia\IbexaAi\Os\Capability\RepositoryCapability;
use Masilia\Contracts\IbexaAi\Os\CapabilityResult;
use Masilia\Contracts\IbexaAi\Os\Preview;
use Masilia\Contracts\IbexaAi\Os\Risk;

final class NotificationsMarkReadCapability extends RepositoryCapability
{
    public function name(): string
    {
        return 'workspace.notifications.mark_read';
    }

    public function summary(): string
    {
        return 'Mark notifications as read (or unread), or all of them.';
    }

    public function description(): string
    {
        return 'Give notification_ids, or all=true for every unread notification. read=false marks the given ones unread again.';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'required' => [], 'properties' => ['notification_ids' => ['type' => 'array', 'minItems' => 1, 'items' => ['type' => 'integer', 'minimum' => 1], 'description' => 'Notification ids, as workspace.notifications.list gives them'], 'all' => ['type' => 'boolean'], 'read' => ['type' => 'boolean']]];
    }

    public function risk(): Risk
    {
        return Risk::WRITE;
    }

    public function requires(): array
    {
        return [['content', 'read']];
    }

    public function examples(): array
    {
        return [['description' => 'Mark everything as read', 'arguments' => ['all' => true]]];
    }

    public function validate(array $arguments): ?string
    {
        return isset($arguments['notification_ids']) === (bool) ($arguments['all'] ?? false) ? 'Give either notification_ids or all=true.' : null;
    }

    public function preview(array $arguments): Preview
    {
        return new Preview(($arguments['read'] ?? true) ? 'Mark notifications as read' : 'Mark notifications as unread', [['Notifications', ($arguments['all'] ?? false) ? 'all' : implode(', ', (array) $arguments['notification_ids'])]]);
    }

    public function execute(array $arguments): CapabilityResult
    {
        $service = $this->repository->getNotificationService();
        $read = (bool) ($arguments['read'] ?? true);
        if ($arguments['all'] ?? false) {
            $service->markUserNotificationsAsRead();

            return CapabilityResult::ok('All notifications are marked as read', []);
        }
        foreach ((array) $arguments['notification_ids'] as $id) {
            $notification = $service->getNotification((int) $id);
            $read ? $service->markNotificationAsRead($notification) : $service->markNotificationAsUnread($notification);
        }

        return CapabilityResult::ok(sprintf('%d notification(s) marked as %s', count((array) $arguments['notification_ids']), $read ? 'read' : 'unread'), []);
    }
}
