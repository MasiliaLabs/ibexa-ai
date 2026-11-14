<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Chat\Thread;

/**
 * The title of a conversation: its first message, on one line and not too long.
 */
final class ThreadTitle
{
    public const DEFAULT = 'New conversation';
    private const MAX = 60;

    /** The longest title a user can give a conversation. */
    public const MAX_USER = 100;

    /**
     * A title the user typed: on one line, cut at the limit. Null when there is nothing to keep.
     */
    public static function fromUser(string $typed): ?string
    {
        $line = trim((string) preg_replace('/\s+/u', ' ', $typed));

        return $line === '' ? null : rtrim(mb_substr($line, 0, self::MAX_USER));
    }

    /** A mention in a message: @[Name](content:ID) or @[Name](content:ID/LOCATION). */
    public const MENTION = '/@\[([^\]\n]+)\]\(content:\d+(?:\/\d+)?\)/u';

    /** A document attached to a message (see the chat's attachments). */
    public const ATTACHMENT = '/\n*<attachment name="([^"]*)" bytes="\d+">\n.*?\n<\/attachment>/su';

    public static function from(string $firstMessage): string
    {
        $attached = [];
        $firstMessage = (string) preg_replace_callback(self::ATTACHMENT, static function (array $match) use (&$attached): string {
            $attached[] = $match[1];

            return '';
        }, $firstMessage);
        if (trim($firstMessage) === '' && $attached !== []) {
            // Nothing written but a document: it names the conversation.
            $firstMessage = 'Attached: ' . $attached[0];
        }

        $line = trim((string) preg_replace('/\s+/u', ' ', (string) preg_replace(self::MENTION, '@$1', $firstMessage)));
        if ($line === '') {
            return self::DEFAULT;
        }

        return mb_strlen($line) > self::MAX ? rtrim(mb_substr($line, 0, self::MAX - 1)) . '…' : $line;
    }
}
