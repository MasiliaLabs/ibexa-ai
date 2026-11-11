<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Tests\Agent\Stream;

use Generator;
use Masilia\Bundle\IbexaAi\Agent\Stream\NeuronRunEvents;
use NeuronAI\Chat\Messages\Stream\Chunks\TextChunk;
use NeuronAI\Chat\Messages\Stream\Chunks\ToolCallChunk;
use NeuronAI\Chat\Messages\Stream\Chunks\ToolResultChunk;
use NeuronAI\Tools\ToolCall;
use NeuronAI\Workflow\WorkflowState;
use PHPUnit\Framework\TestCase;

final class NeuronRunEventsTest extends TestCase
{
    public function testAnAnswerAfterAToolCallIsShownWhenTheReasoningBeforeTheCallWasNeverClosed(): void
    {
        // What MiniMax does: the message that ends in a tool call opens <think> and never closes it.
        $call = new ToolCall('browse_location', 'call_1', ['location_id' => 1116]);
        $call->setResult('{"success":true,"message":"Found 3 items","data":{}}');

        $events = $this->events([
            new TextChunk('m1', '<think>The user wants a summary. '),
            new TextChunk('m1', "Let's call."),
            new ToolCallChunk('m1', $call),
            new ToolResultChunk($call),
            new TextChunk('m2', 'The About page has three blocks.'),
        ]);

        self::assertSame('The About page has three blocks.', $this->text($events));
        self::assertSame('run.completed', end($events)['event']);
    }

    public function testReasoningInsideOneMessageIsStillHidden(): void
    {
        $events = $this->events([
            new TextChunk('m1', '<think>plan the '),
            new TextChunk('m1', 'answer</think>'),
            new TextChunk('m1', "\n\nDone."),
        ]);

        self::assertSame("\n\nDone.", $this->text($events));
    }

    public function testAMessageWithReasoningOnlyEndsWithANoAnswerError(): void
    {
        $events = $this->events([new TextChunk('m1', '<think>I will check the fields first')]);

        $last = end($events);
        self::assertSame('run.error', $last['event']);
        self::assertSame('no_answer', $last['data']['code']);
    }

    /**
     * @param list<object> $chunks
     * @return list<array{event: string, data: array<string, mixed>}>
     */
    private function events(array $chunks): array
    {
        $run = (static function () use ($chunks): Generator {
            yield from $chunks;

            return new WorkflowState();
        })();

        $events = [];
        foreach ((new NeuronRunEvents())($run, static fn (): array => [], static function (): void {}) as $frame) {
            if (preg_match('/^event: (\S+)\ndata: (.*)\n\n$/s', $frame, $match) === 1) {
                $events[] = ['event' => $match[1], 'data' => json_decode($match[2], true, 512, JSON_THROW_ON_ERROR)];
            }
        }

        return $events;
    }

    /**
     * @param list<array{event: string, data: array<string, mixed>}> $events
     */
    private function text(array $events): string
    {
        $text = '';
        foreach ($events as $event) {
            if ($event['event'] === 'text.delta') {
                $text .= $event['data']['text'];
            }
        }

        return $text;
    }
}
