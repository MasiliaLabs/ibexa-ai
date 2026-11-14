<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Tests\Bundle\Chat\Thread;

use Masilia\Bundle\IbexaAi\Chat\Thread\ThreadId;
use Masilia\Bundle\IbexaAi\Chat\Thread\ThreadTitle;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ThreadTitleTest extends TestCase
{
    public function testTheTitleIsTheFirstMessageOnOneLine(): void
    {
        self::assertSame('Create a folder under location 1018', ThreadTitle::from("  Create a folder\n under   location 1018 \n"));
    }

    public function testALongMessageIsCutWithAnEllipsisWithoutBreakingACharacter(): void
    {
        $title = ThreadTitle::from(str_repeat('é', 200));

        self::assertSame(60, mb_strlen($title));
        self::assertStringEndsWith('…', $title);
    }

    public function testNothingToReadGivesTheDefaultTitle(): void
    {
        self::assertSame(ThreadTitle::DEFAULT, ThreadTitle::from(" \n\t "));
    }

    public function testATypedTitleIsKeptOnOneLineAndCutAtTheLimit(): void
    {
        self::assertSame('Site review', ThreadTitle::fromUser("  Site \n review "));
        self::assertSame(100, mb_strlen((string) ThreadTitle::fromUser(str_repeat('é', 300))));
    }

    public function testATypedTitleWithNothingInItIsNotAnAnswer(): void
    {
        self::assertNull(ThreadTitle::fromUser(" \n\t "));
        self::assertNull(ThreadTitle::fromUser(''));
    }

    #[DataProvider('ids')]
    public function testOnlyPlainIdentifiersAreAccepted(mixed $id, bool $valid): void
    {
        self::assertSame($valid, ThreadId::isValid($id));
    }

    /**
     * @return iterable<string, array{mixed, bool}>
     */
    public static function ids(): iterable
    {
        yield 'a uuid' => ['3f2b8c1e-5d6a-4e7f-9a10-2b3c4d5e6f70', true];
        yield 'the former id of a conversation' => ['agent-14-admin_fossil_exit', true];
        yield 'too short' => ['abc', false];
        yield 'too long' => [str_repeat('a', 65), false];
        yield 'a slash' => ['../../etc/passwd-x', false];
        yield 'a space' => ['abcdefgh ijkl', false];
        yield 'a quote' => ["abcdefgh'; DROP", false];
        yield 'not a string' => [12345678, false];
        yield 'null' => [null, false];
    }
}
