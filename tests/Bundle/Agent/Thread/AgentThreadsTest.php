<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Tests\Bundle\Agent\Thread;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Masilia\Bundle\IbexaAi\Agent\Thread\AgentThreads;
use Masilia\Bundle\IbexaAi\Agent\Thread\ThreadAccessDeniedException;
use PHPUnit\Framework\TestCase;

final class AgentThreadsTest extends TestCase
{
    private Connection $connection;
    private AgentThreads $threads;

    protected function setUp(): void
    {
        $this->connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $this->connection->executeStatement('CREATE TABLE app_ai_agent_thread (
            id VARCHAR(64) PRIMARY KEY, user_id INT NOT NULL, siteaccess VARCHAR(100) NOT NULL, title VARCHAR(160) NOT NULL,
            created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL)');
        $this->threads = new AgentThreads($this->connection);
    }

    private function at(string $time): DateTimeImmutable
    {
        return new DateTimeImmutable($time . ' UTC');
    }

    public function testTheFirstMessageCreatesTheConversationAndNamesIt(): void
    {
        $this->threads->claim('thread-aaaa-0001', 14, 'admin', "Create a folder\nnamed X", $this->at('2026-11-13 10:00:00'));

        $thread = $this->threads->owned('thread-aaaa-0001', 14);

        self::assertNotNull($thread);
        self::assertSame('Create a folder named X', $thread->title);
        self::assertSame('2026-11-13T10:00:00+00:00', $thread->updatedAt);
    }

    public function testLaterMessagesMoveItUpTheHistoryWithoutRenamingIt(): void
    {
        $this->threads->claim('thread-aaaa-0001', 14, 'admin', 'First', $this->at('2026-11-13 10:00:00'));
        $this->threads->claim('thread-bbbb-0002', 14, 'admin', 'Second', $this->at('2026-11-13 11:00:00'));
        $this->threads->claim('thread-aaaa-0001', 14, 'admin', 'Another message', $this->at('2026-11-13 12:00:00'));

        $list = $this->threads->list(14, 'admin');

        self::assertSame(['thread-aaaa-0001', 'thread-bbbb-0002'], array_map(static fn ($t): string => $t->id, $list));
        self::assertSame('First', $list[0]->title);
        self::assertSame('thread-aaaa-0001', $this->threads->latest(14, 'admin')?->id);
    }

    public function testAConversationOfAnotherUserCannotBeUsedNorSeen(): void
    {
        $this->threads->claim('thread-aaaa-0001', 14, 'admin', 'Mine', $this->at('2026-11-13 10:00:00'));

        self::assertNull($this->threads->owned('thread-aaaa-0001', 15));
        self::assertSame([], $this->threads->list(15, 'admin'));
        self::assertFalse($this->threads->delete('thread-aaaa-0001', 15));
        self::assertNotNull($this->threads->owned('thread-aaaa-0001', 14), 'the owner still has it');

        $this->expectException(ThreadAccessDeniedException::class);
        $this->threads->claim('thread-aaaa-0001', 15, 'admin', 'Taking it');
    }

    public function testTheHistoryIsPerSiteaccess(): void
    {
        $this->threads->claim('thread-aaaa-0001', 14, 'admin', 'A', $this->at('2026-11-13 10:00:00'));
        $this->threads->claim('thread-bbbb-0002', 14, 'admin_fossil_exit', 'B', $this->at('2026-11-13 11:00:00'));

        self::assertSame(['thread-bbbb-0002'], array_map(static fn ($t): string => $t->id, $this->threads->list(14, 'admin_fossil_exit')));
        self::assertNull($this->threads->latest(14, 'admin_mattcch'));
    }

    public function testRenamingChangesTheTitleAndKeepsTheConversationWhereItWas(): void
    {
        $this->threads->claim('thread-aaaa-0001', 14, 'admin', 'First', $this->at('2026-11-13 10:00:00'));
        $this->threads->claim('thread-bbbb-0002', 14, 'admin', 'Second', $this->at('2026-11-13 11:00:00'));

        self::assertTrue($this->threads->rename('thread-aaaa-0001', 14, 'Site review'));

        $list = $this->threads->list(14, 'admin');
        self::assertSame(['thread-bbbb-0002', 'thread-aaaa-0001'], array_map(static fn ($t): string => $t->id, $list));
        self::assertSame('Site review', $list[1]->title);
        self::assertSame('2026-11-13T10:00:00+00:00', $list[1]->updatedAt, 'renaming is not using');
    }

    public function testAConversationOfAnotherUserOrNoneCannotBeRenamed(): void
    {
        $this->threads->claim('thread-aaaa-0001', 14, 'admin', 'Mine', $this->at('2026-11-13 10:00:00'));

        self::assertFalse($this->threads->rename('thread-aaaa-0001', 15, 'Stolen'));
        self::assertFalse($this->threads->rename('thread-zzzz-9999', 14, 'Nothing'));
        self::assertSame('Mine', $this->threads->owned('thread-aaaa-0001', 14)?->title);
    }

    public function testDeletingForgetsTheConversation(): void
    {
        $this->threads->claim('thread-aaaa-0001', 14, 'admin', 'A', $this->at('2026-11-13 10:00:00'));

        self::assertTrue($this->threads->delete('thread-aaaa-0001', 14));
        self::assertNull($this->threads->owned('thread-aaaa-0001', 14));
        self::assertFalse($this->threads->delete('thread-aaaa-0001', 14));
    }
}
