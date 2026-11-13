<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Agent\Thread;

/**
 * The title of a conversation: its first message, on one line and not too long.
 */
final class ThreadTitle
{
    public const DEFAULT = 'New conversation';
    private const MAX = 60;

    public static function from(string $firstMessage): string
    {
        $line = trim((string) preg_replace('/\s+/u', ' ', $firstMessage));
        if ($line === '') {
            return self::DEFAULT;
        }

        return mb_strlen($line) > self::MAX ? rtrim(mb_substr($line, 0, self::MAX - 1)) . '…' : $line;
    }
}
