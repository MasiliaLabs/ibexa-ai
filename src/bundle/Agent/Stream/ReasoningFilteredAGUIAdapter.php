<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Agent\Stream;

use Masilia\IbexaAi\Suggestion\ReasoningFilter;
use NeuronAI\Agent\Adapters\AGUIAdapter;
use NeuronAI\Agent\Interrupt\ApprovalRequest;
use NeuronAI\Chat\Messages\Stream\Chunks\TextChunk;
use NeuronAI\Chat\Messages\Stream\Chunks\ToolCallChunk;
use NeuronAI\Tools\ToolCall;
use NeuronAI\Workflow\Interrupt\InterruptRequest;

/**
 * Neuron's AG-UI adapter, with the reasoning of models that write it inline (<think>…</think>) removed from
 * the visible text. The adapter streams what the model writes, and a reasoning model such as MiniMax writes
 * its thoughts there.
 */
final class ReasoningFilteredAGUIAdapter extends AGUIAdapter
{
    private ReasoningFilter $reasoning;

    /**
     * @param list<array<string, mixed>> $messages
     * @param array<string, mixed> $state
     */
    public function __construct(string $threadId, ?string $runId = null, array $messages = [], array $state = [])
    {
        parent::__construct($threadId, $runId, $messages, $state);
        $this->reasoning = new ReasoningFilter();
    }

    /**
     * Neuron publishes a tool call when it dispatches it, so a call that waits for approval is never published:
     * the client would get an interrupt naming a call it has never seen, and could not show the approval on it.
     * The call is published first.
     */
    public function interrupt(InterruptRequest $request): iterable
    {
        if ($request instanceof ApprovalRequest && !$this->runFailed && !$this->finished) {
            foreach ($request->getActions() as $action) {
                yield from $this->publishToolCall(new ToolCall($action->name, $action->id, $action->inputs, $action->description));
            }
        }

        yield from parent::interrupt($request);
    }

    /**
     * Neuron describes the approval of a tool call as a free-standing `confirmation`. AG-UI's own shape for it is
     * a `tool_call` interrupt that names the call, which is what clients read to show the approval on the call.
     * The answer is matched by the interrupt id (the call id), which does not change.
     *
     * @return list<array<string, mixed>>
     */
    protected function confirmations(ApprovalRequest $request): array
    {
        return array_map(
            static fn (array $interrupt): array => ['reason' => 'tool_call', 'toolCallId' => $interrupt['id']] + $interrupt,
            parent::confirmations($request),
        );
    }

    public function reset(): void
    {
        parent::reset();
        $this->reasoning = new ReasoningFilter();
    }

    public function transform(object $chunk): iterable
    {
        if ($chunk instanceof TextChunk) {
            $visible = $this->reasoning->feed($chunk->content);
            if ($visible === '') {
                return [];
            }
            $chunk = new TextChunk($chunk->messageId, $visible);
        } elseif ($chunk instanceof ToolCallChunk) {
            // MiniMax does not close <think> when its message ends in a tool call: the next message starts clean.
            $this->reasoning = new ReasoningFilter();
        }

        return parent::transform($chunk);
    }
}
