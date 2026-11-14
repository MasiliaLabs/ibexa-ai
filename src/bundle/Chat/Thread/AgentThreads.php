<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Chat\Thread;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Masilia\Bundle\IbexaAi\Chat\DTO\AgentThread;

/**
 * The conversations of the users: who owns each, its title and when it last moved. The messages themselves are in
 * Neuron's own store, under the same id.
 *
 * Every read and write is for one user: a conversation of someone else is not found, and cannot be claimed.
 */
readonly class AgentThreads
{
    public const TABLE = 'app_ai_agent_thread';
    private const FORMAT = 'Y-m-d H:i:s';

    public function __construct(private Connection $connection)
    {
    }

    /**
     * Records that the user writes in this conversation: it is created on the first message, and moves up the
     * history on each one.
     *
     * @throws ThreadAccessDeniedException when the conversation is another user's
     */
    public function claim(string $id, int $userId, string $siteaccess, string $firstMessage, ?DateTimeImmutable $now = null): void
    {
        $now = $this->utc($now);
        $row = $this->row($id);

        if ($row === null) {
            try {
                $this->connection->insert(self::TABLE, [
                    'id' => $id,
                    'user_id' => $userId,
                    'siteaccess' => $siteaccess,
                    'title' => ThreadTitle::from($firstMessage),
                    'created_at' => $now->format(self::FORMAT),
                    'updated_at' => $now->format(self::FORMAT),
                ]);

                return;
            } catch (UniqueConstraintViolationException) {
                // Created meanwhile by another request: it is checked like an existing one.
                $row = $this->row($id);
            }
        }

        if ($row === null || (int) $row['user_id'] !== $userId) {
            throw new ThreadAccessDeniedException(sprintf('Conversation "%s" is not yours.', $id));
        }

        $this->connection->update(self::TABLE, ['updated_at' => $now->format(self::FORMAT)], ['id' => $id]);
    }

    /** The user's conversation, or null when there is none by this id (or it is someone else's). */
    public function owned(string $id, int $userId): ?AgentThread
    {
        $row = $this->row($id);

        return $row !== null && (int) $row['user_id'] === $userId ? $this->thread($row) : null;
    }

    /**
     * @return list<AgentThread> The most recently used first
     */
    public function list(int $userId, string $siteaccess, int $limit = 50): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT * FROM ' . self::TABLE . ' WHERE user_id = ? AND siteaccess = ? ORDER BY updated_at DESC, created_at DESC LIMIT ' . max(1, $limit),
            [$userId, $siteaccess],
        );

        return array_map($this->thread(...), $rows);
    }

    public function latest(int $userId, string $siteaccess): ?AgentThread
    {
        return $this->list($userId, $siteaccess, 1)[0] ?? null;
    }

    /**
     * Gives the user's conversation a new title. It stays where it is in the history: renaming is not using.
     * False when the user has no such conversation.
     */
    public function rename(string $id, int $userId, string $title): bool
    {
        if ($this->owned($id, $userId) === null) {
            return false;
        }
        $this->connection->update(self::TABLE, ['title' => $title], ['id' => $id, 'user_id' => $userId]);

        return true;
    }

    /** Forgets the conversation of the user. False when there was none. */
    public function delete(string $id, int $userId): bool
    {
        return $this->connection->delete(self::TABLE, ['id' => $id, 'user_id' => $userId]) > 0;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function row(string $id): ?array
    {
        $row = $this->connection->fetchAssociative('SELECT * FROM ' . self::TABLE . ' WHERE id = ?', [$id]);

        return $row === false ? null : $row;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function thread(array $row): AgentThread
    {
        return new AgentThread(
            (string) $row['id'],
            (int) $row['user_id'],
            (string) $row['siteaccess'],
            (string) $row['title'],
            (new DateTimeImmutable((string) $row['updated_at'], new DateTimeZone('UTC')))->format(DATE_ATOM),
        );
    }

    private function utc(?DateTimeImmutable $now): DateTimeImmutable
    {
        return ($now ?? new DateTimeImmutable('now'))->setTimezone(new DateTimeZone('UTC'));
    }
}
