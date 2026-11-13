<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Os\Capability\Workspace;

use Masilia\Bundle\IbexaAi\Os\Capability\RepositoryCapability;
use Masilia\IbexaAi\Os\CapabilityResult;
use Masilia\IbexaAi\Os\Risk;

final class NotificationsListCapability extends RepositoryCapability
{
    public function name(): string
    {
        return 'workspace.notifications.list';
    }

    public function summary(): string
    {
        return 'List the notifications of the user.';
    }

    public function description(): string
    {
        return 'Returns the user\'s notifications, newest first: id, type, whether it is unread, date and a short text of its data. Also says how many are unread.';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'required' => [], 'properties' => ['limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100], 'only_unread' => ['type' => 'boolean']]];
    }

    public function risk(): Risk
    {
        return Risk::READ;
    }

    public function requires(): array
    {
        return [['content', 'read']];
    }

    public function examples(): array
    {
        return [['description' => 'Unread notifications', 'arguments' => ['only_unread' => true]]];
    }

    public function execute(array $arguments): CapabilityResult
    {
        $service = $this->repository->getNotificationService();
        $only = (bool) ($arguments['only_unread'] ?? false);
        $list = $service->loadNotifications(0, (int) ($arguments['limit'] ?? 25));
        $rows = [];
        foreach ($list->items as $notification) {
            if ($only && !$notification->isPending) {
                continue;
            }
            $rows[] = ['notification_id' => $notification->id, 'type' => $notification->type, 'unread' => $notification->isPending, 'created' => $notification->created->format('Y-m-d H:i'), 'data' => mb_substr((string) json_encode($notification->data, JSON_UNESCAPED_UNICODE), 0, 200)];
        }

        return CapabilityResult::ok(sprintf('%d notification(s), %d unread', $list->totalCount, $service->getPendingNotificationCount()), ['notifications' => $rows, 'total' => $list->totalCount, 'unread' => $service->getPendingNotificationCount()]);
    }
}
