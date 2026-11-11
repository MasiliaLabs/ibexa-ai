<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Tests\Agent\Stream;

use Masilia\IbexaAi\Agent\Stream\ToolOutcome;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ToolOutcome::class)]
final class ToolOutcomeTest extends TestCase
{
    public function testAToolJsonAnswerIsReadAsIs(): void
    {
        $outcome = ToolOutcome::fromRaw('{"success":true,"message":"Created","data":{"content_id":12}}');

        self::assertTrue($outcome['ok']);
        self::assertSame('Created', $outcome['message']);
        self::assertSame(['content_id' => 12], $outcome['data']);
    }

    public function testAFailedToolIsNotOk(): void
    {
        self::assertFalse(ToolOutcome::fromRaw('{"success":false,"message":"Not found"}')['ok']);
    }

    public function testARejectedCallIsNotReportedAsASuccess(): void
    {
        $outcome = ToolOutcome::fromRaw('TOOL NOT EXECUTED. The user rejected this action.');

        self::assertFalse($outcome['ok']);
        self::assertStringContainsString('rejected', $outcome['message']);
    }

    public function testPlainTextIsAnOkMessageAndLongTextIsClipped(): void
    {
        $outcome = ToolOutcome::fromRaw(str_repeat('a', 900));

        self::assertTrue($outcome['ok']);
        self::assertLessThanOrEqual(501, mb_strlen($outcome['message']));
    }
}
