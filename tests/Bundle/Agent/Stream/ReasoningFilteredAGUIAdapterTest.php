<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Tests\Bundle\Agent\Stream;

use Masilia\Bundle\IbexaAi\Agent\Stream\ReasoningFilteredAGUIAdapter;
use NeuronAI\Agent\Interrupt\Action;
use NeuronAI\Agent\Interrupt\ApprovalRequest;
use NeuronAI\Chat\Messages\Stream\Chunks\TextChunk;
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
        self::assertSame([], $types, 'a chunk that is only reasoning produces nothing');

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
}
