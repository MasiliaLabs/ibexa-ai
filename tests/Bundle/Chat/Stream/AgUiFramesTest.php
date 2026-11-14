<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Tests\Bundle\Chat\Stream;

use Generator;
use Masilia\Bundle\IbexaAi\Chat\Stream\AgUiFrames;
use Masilia\Bundle\IbexaAi\Chat\Stream\ReasoningFilteredAGUIAdapter;
use NeuronAI\Workflow\Streaming\ProtocolEvent;
use PHPUnit\Framework\TestCase;

final class AgUiFramesTest extends TestCase
{
    /**
     * @param list<ProtocolEvent> $events
     */
    private function events(array $events): Generator
    {
        foreach ($events as $event) {
            yield $event;
        }
    }

    public function testEventsBecomeDataFramesAndTheKeepAliveBecomesAComment(): void
    {
        $frames = iterator_to_array(AgUiFrames::encode($this->events([
            new ProtocolEvent('RUN_STARTED', ['runId' => 'r']),
            new ProtocolEvent(ReasoningFilteredAGUIAdapter::KEEP_ALIVE),
            new ProtocolEvent('TEXT_MESSAGE_CONTENT', ['delta' => 'é']),
        ])), false);

        self::assertSame(
            ["data: {\"type\":\"RUN_STARTED\",\"runId\":\"r\"}\n\n", ": keep-alive\n\n", "data: {\"type\":\"TEXT_MESSAGE_CONTENT\",\"delta\":\"\\u00e9\"}\n\n"],
            $frames,
        );
    }

    public function testTheKeepAliveIsNeverAnAgUiEvent(): void
    {
        $frames = iterator_to_array(AgUiFrames::encode($this->events([new ProtocolEvent(ReasoningFilteredAGUIAdapter::KEEP_ALIVE)])), false);

        self::assertSame([': keep-alive' . "\n\n"], $frames);
        self::assertStringNotContainsString('data:', $frames[0]);
    }

    public function testAnEventTheWireCannotCarryFailsTheRunInsteadOfBeingDropped(): void
    {
        $run = (function (): Generator {
            yield new ProtocolEvent('CUSTOM', ['value' => INF]);
        })();

        $this->expectException(\JsonException::class);
        iterator_to_array(AgUiFrames::encode($run), false);
    }
}
