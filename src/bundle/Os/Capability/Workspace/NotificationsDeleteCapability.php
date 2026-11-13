<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Os\Capability\Workspace;

use Masilia\Bundle\IbexaAi\Os\Capability\RepositoryCapability;
use Masilia\IbexaAi\Os\CapabilityResult;
use Masilia\IbexaAi\Os\Preview;
use Masilia\IbexaAi\Os\Risk;

final class NotificationsDeleteCapability extends RepositoryCapability
{
    public function name(): string
    {
        return 'workspace.notifications.delete';
    }

    public function summary(): string
    {
        return 'Delete notifications of the user.';
    }

    public function description(): string
    {
        return 'Deletes the given notifications for good; they are the user\'s own.';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'required' => ['notification_ids'], 'properties' => ['notification_ids' => ['type' => 'array', 'minItems' => 1, 'items' => ['type' => 'integer', 'minimum' => 1], 'description' => 'Notification ids, as workspace.notifications.list gives them']]];
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
        return [['description' => 'Clear two notifications', 'arguments' => ['notification_ids' => [12, 13]]]];
    }

    public function preview(array $arguments): Preview
    {
        return new Preview('Delete notifications', [['Notifications', implode(', ', (array) $arguments['notification_ids'])]], ['They are deleted for good.']);
    }

    public function execute(array $arguments): CapabilityResult
    {
        $service = $this->repository->getNotificationService();
        foreach ((array) $arguments['notification_ids'] as $id) {
            $service->deleteNotification($service->getNotification((int) $id));
        }

        return CapabilityResult::ok(sprintf('%d notification(s) deleted', count((array) $arguments['notification_ids'])), []);
    }
}
