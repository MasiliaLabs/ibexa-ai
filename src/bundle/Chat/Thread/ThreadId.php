<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Chat\Thread;

/**
 * The identifier of a conversation. The client makes one up for a new conversation (a UUID), so it is checked
 * before it is used as a key: letters, digits, dash and underscore. Whose conversation it is, is a separate matter
 * (see AgentThreads).
 */
final class ThreadId
{
    public static function isValid(mixed $id): bool
    {
        return is_string($id) && preg_match('/^[A-Za-z0-9_-]{8,64}$/', $id) === 1;
    }
}
