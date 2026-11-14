<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Os\Audit;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Connection;
use Masilia\Contracts\IbexaAi\Os\Risk;

/**
 * Who had the assistant do what, with what result: one row per capability that changes something, and per failure.
 * What a call carried is kept so that it can be understood later, cut when it is long.
 */
final readonly class AuditLog
{
    public const TABLE = 'app_ai_audit';
    private const MAX_ARGUMENTS = 20000;
    private const MAX_MESSAGE = 500;

    public function __construct(private Connection $connection)
    {
    }

    /**
     * @param array<string, mixed> $arguments
     */
    public function record(int $userId, ?string $threadId, string $capability, Risk $risk, array $arguments, bool $success, string $message, ?DateTimeImmutable $now = null): void
    {
        $json = (string) json_encode($arguments, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        $this->connection->insert(self::TABLE, [
            'user_id' => $userId,
            'thread_id' => $threadId,
            'capability' => $capability,
            'risk' => $risk->value,
            'arguments' => mb_strlen($json) > self::MAX_ARGUMENTS ? mb_substr($json, 0, self::MAX_ARGUMENTS) . '…' : $json,
            'success' => $success ? 1 : 0,
            'message' => mb_substr($message, 0, self::MAX_MESSAGE),
            'created_at' => ($now ?? new DateTimeImmutable('now'))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * The latest records, most recent first.
     *
     * @return list<array<string, mixed>>
     */
    public function latest(int $limit = 50): array
    {
        return $this->connection->fetchAllAssociative('SELECT * FROM ' . self::TABLE . ' ORDER BY id DESC LIMIT ' . max(1, $limit));
    }
}
