<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Tests\Lib\Chat\Stream;

use Masilia\IbexaAi\Chat\Stream\AlreadyDecidedException;
use Masilia\IbexaAi\Chat\Stream\DecisionPendingException;
use Masilia\IbexaAi\Chat\Stream\NoAnswerException;
use Masilia\IbexaAi\Chat\Stream\ReasoningFilteredAGUIAdapter;
use Masilia\Contracts\IbexaAi\Provider\ProviderConfigurationException;
use NeuronAI\Agent\Interrupt\Action;
use NeuronAI\Agent\Interrupt\ApprovalRequest;
use NeuronAI\Chat\Messages\Stream\Chunks\TextChunk;
use NeuronAI\Chat\Messages\Stream\Chunks\ToolCallChunk;
use NeuronAI\Chat\Messages\Stream\Chunks\ToolResultChunk;
use NeuronAI\Exceptions\RunInFlightException;
use NeuronAI\Exceptions\ToolRunsExceededException;
use NeuronAI\Tools\ToolCall;
use NeuronAI\Workflow\WorkflowStatus;
use NeuronAI\Workflow\Streaming\ProtocolEvent;
use PHPUnit\Framework\TestCase;

final class ReasoningFilteredAGUIAdapterTest extends TestCase
{
    /**
     * @param iterable<ProtocolEvent> $events
     * @return list<array<string, mixed>>
     */
    private function payloads(iterable $events): array
    {
        $out = [];
        foreach ($events as $event) {
            $out[] = json_decode((string) json_encode($event), true);
        }

        return $out;
    }

    private function request(): ApprovalRequest
    {
        return new ApprovalRequest('Approve?', [new Action('call_1', 'propose_plan', '{"title":"X"}', reason: 'Create a folder', inputs: ['title' => 'X'])]);
    }

    public function testTheReasoningOfAModelIsNotStreamed(): void
    {
        $adapter = new ReasoningFilteredAGUIAdapter('thread', 'run');
        iterator_to_array($adapter->start(), false);

        $types = array_column($this->payloads($adapter->transform(new TextChunk('m1', '<think>secret thoughts</think>'))), 'type');
        self::assertSame([ReasoningFilteredAGUIAdapter::KEEP_ALIVE], $types, 'a chunk that is only reasoning shows nothing, and only keeps the stream active');

        $events = $this->payloads($adapter->transform(new TextChunk('m1', 'Hello')));
        $deltas = implode('', array_column(array_filter($events, static fn (array $e): bool => $e['type'] === 'TEXT_MESSAGE_CONTENT'), 'delta'));
        self::assertSame('Hello', $deltas);
    }

    public function testAnApprovalIsAToolCallInterruptThatNamesTheCall(): void
    {
        $adapter = new ReasoningFilteredAGUIAdapter('thread', 'run');
        iterator_to_array($adapter->start(), false);

        $events = $this->payloads($adapter->interrupt($this->request()));
        $finished = $events[array_key_last($events)];

        self::assertSame('RUN_FINISHED', $finished['type']);
        self::assertSame('interrupt', $finished['outcome']['type']);
        $interrupt = $finished['outcome']['interrupts'][0];
        self::assertSame('tool_call', $interrupt['reason']);
        self::assertSame('call_1', $interrupt['id']);
        self::assertSame('call_1', $interrupt['toolCallId']);
        self::assertSame('Create a folder', $interrupt['message']);
        self::assertSame(['type' => 'boolean'], $interrupt['responseSchema']['properties']['approved']);
    }

    public function testTheCallThatWaitsForApprovalIsPublishedBeforeTheInterrupt(): void
    {
        $adapter = new ReasoningFilteredAGUIAdapter('thread', 'run');
        iterator_to_array($adapter->start(), false);

        $types = array_column($this->payloads($adapter->interrupt($this->request())), 'type');

        self::assertSame(['TOOL_CALL_START', 'TOOL_CALL_ARGS', 'TOOL_CALL_END', 'STATE_SNAPSHOT', 'MESSAGES_SNAPSHOT', 'RUN_FINISHED'], $types);
    }

    public function testTheSnapshotHoldsTheCallSoAReloadedClientCanShowIt(): void
    {
        $adapter = new ReasoningFilteredAGUIAdapter('thread', 'run');
        iterator_to_array($adapter->start(), false);

        $events = $this->payloads($adapter->interrupt($this->request()));
        $snapshot = array_values(array_filter($events, static fn (array $e): bool => $e['type'] === 'MESSAGES_SNAPSHOT'))[0];
        $calls = $snapshot['messages'][0]['toolCalls'];

        self::assertSame('call_1', $calls[0]['id']);
        self::assertSame('propose_plan', $calls[0]['function']['name']);
    }

    /**
     * @param list<object> $chunks
     * @return list<array<string, mixed>> the events of a whole run, without the keep-alive ones
     */
    private function wholeRun(array $chunks): array
    {
        $adapter = new ReasoningFilteredAGUIAdapter('thread', 'run');
        $events = $this->payloads($adapter->start());
        foreach ($chunks as $chunk) {
            array_push($events, ...$this->payloads($adapter->transform($chunk)));
        }
        array_push($events, ...$this->payloads($adapter->end()));

        return array_values(array_filter($events, static fn (array $e): bool => $e['type'] !== ReasoningFilteredAGUIAdapter::KEEP_ALIVE));
    }

    /**
     * @param list<array<string, mixed>> $events
     */
    private function text(array $events): string
    {
        return implode('', array_column(array_filter($events, static fn (array $e): bool => $e['type'] === 'TEXT_MESSAGE_CONTENT'), 'delta'));
    }

    public function testAnAnswerAfterAToolCallIsShownWhenTheReasoningBeforeTheCallWasNeverClosed(): void
    {
        // What MiniMax does: the message that ends in a tool call opens <think> and never closes it.
        $call = new ToolCall('browse_location', 'call_1', ['location_id' => 1116]);
        $call->setResult('{"success":true,"message":"Found 3 items","data":{}}');

        $events = $this->wholeRun([
            new TextChunk('m1', '<think>The user wants a summary. '),
            new TextChunk('m1', "Let's call."),
            new ToolCallChunk('m1', $call),
            new ToolResultChunk($call),
            new TextChunk('m2', 'The About page has three blocks.'),
        ]);

        self::assertSame('The About page has three blocks.', $this->text($events));
        self::assertSame('RUN_FINISHED', end($events)['type']);
    }

    public function testReasoningInsideOneMessageIsStillHidden(): void
    {
        $events = $this->wholeRun([
            new TextChunk('m1', '<think>plan the '),
            new TextChunk('m1', 'answer</think>'),
            new TextChunk('m1', "\n\nDone."),
        ]);

        self::assertSame("\n\nDone.", $this->text($events));
    }

    public function testAMessageWithReasoningOnlyEndsWithANoAnswerError(): void
    {
        $events = $this->wholeRun([new TextChunk('m1', '<think>I will check the fields first')]);

        $last = end($events);
        self::assertSame('RUN_ERROR', $last['type']);
        self::assertStringContainsString('stopped without answering', $last['message']);
        self::assertNotContains('RUN_FINISHED', array_column($events, 'type'), 'a failed run is not also finished');
    }

    public function testAnAnswerThatOnlyUsesToolsIsNotAnError(): void
    {
        $call = new ToolCall('explore_site', 'call_1');
        $call->setResult('{"success":true,"message":"ok","data":{}}');

        $events = $this->wholeRun([new ToolCallChunk('m1', $call), new ToolResultChunk($call)]);

        self::assertSame('RUN_FINISHED', end($events)['type']);
    }

    public function testWhileTheModelThinksEveryChunkKeepsTheStreamActive(): void
    {
        $adapter = new ReasoningFilteredAGUIAdapter('thread', 'run');
        iterator_to_array($adapter->start(), false);

        foreach (['<think>step one ', 'step two ', 'step three '] as $piece) {
            self::assertSame([ReasoningFilteredAGUIAdapter::KEEP_ALIVE], array_column($this->payloads($adapter->transform(new TextChunk('m1', $piece))), 'type'));
        }
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('failures')]
    public function testEachKnownFailureSaysWhatToDo(\Throwable $failure, string $expected): void
    {
        $adapter = new ReasoningFilteredAGUIAdapter('thread', 'run');
        iterator_to_array($adapter->start(), false);

        $events = $this->payloads($adapter->error($failure));

        self::assertSame('RUN_ERROR', end($events)['type']);
        self::assertStringContainsString($expected, end($events)['message']);
        self::assertStringNotContainsString('api.minimax', end($events)['message'], 'internals never reach the user');
    }

    /**
     * @return iterable<string, array{\Throwable, string}>
     */
    public static function failures(): iterable
    {
        yield 'run in flight' => [new RunInFlightException('w', 'r', WorkflowStatus::Running, 1), 'still being finished'];
        yield 'too many steps' => [new ToolRunsExceededException('Tool x has been executed too many times - 10'), 'too many steps'];
        yield 'no provider' => [new ProviderConfigurationException('No AI provider is configured for this siteaccess.'), 'No AI provider is configured'];
        yield 'anything else' => [new \RuntimeException('Network error during POST https://api.minimax.io/v1/chat/completions: Timeout'), 'currently unavailable'];
        yield 'no answer' => [new NoAnswerException(), 'stopped without answering'];
        yield 'decided in another window' => [new AlreadyDecidedException('x'), 'already decided'];
        yield 'decision pending' => [new DecisionPendingException('x'), 'Approve or refuse it first'];
    }
}
