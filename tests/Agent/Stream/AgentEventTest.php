<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Tests\Agent\Stream;

use Masilia\IbexaAi\Agent\Stream\AgentEvent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AgentEvent::class)]
final class AgentEventTest extends TestCase
{
    public function testEventIsANamedServerSentEventWithTheProtocolVersion(): void
    {
        self::assertSame(
            "event: text.delta\ndata: {\"v\":1,\"text\":\"Bonjour\"}\n\n",
            AgentEvent::encode(AgentEvent::TEXT_DELTA, ['text' => 'Bonjour']),
        );
    }

    public function testUnicodeIsNotEscaped(): void
    {
        self::assertStringContainsString('"text":"é à"', AgentEvent::encode(AgentEvent::TEXT_DELTA, ['text' => 'é à']));
    }

    public function testMultiLineTextStaysInOneDataLine(): void
    {
        $frame = AgentEvent::encode(AgentEvent::TEXT_DELTA, ['text' => "line 1\nline 2"]);

        // One event line, one data line, and the blank line that ends the frame.
        self::assertSame(1, substr_count($frame, "data: "));
        self::assertSame(1, substr_count($frame, "event: "));
        self::assertStringEndsWith("\n\n", $frame);
        self::assertSame(3, substr_count($frame, "\n"));
    }
}
