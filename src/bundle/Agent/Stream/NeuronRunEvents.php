<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Agent\Stream;

use Generator;
use Masilia\IbexaAi\Agent\Stream\AgentEvent;
use Masilia\IbexaAi\Agent\Stream\ToolOutcome;
use Masilia\IbexaAi\Suggestion\ReasoningFilter;
use NeuronAI\Chat\Messages\Stream\Chunks\TextChunk;
use NeuronAI\Chat\Messages\Stream\Chunks\ToolCallChunk;
use NeuronAI\Chat\Messages\Stream\Chunks\ToolResultChunk;
use NeuronAI\Workflow\WorkflowState;
use Throwable;

/**
 * Turns a Neuron run into the agent stream: model text, tool calls and their outcomes,
 * pending approvals, then a terminal event. Yields encoded SSE strings.
 */
final class NeuronRunEvents
{
    private const ERROR_MESSAGE = 'The AI agent is currently unavailable. Please try again later or contact an administrator.';

    /**
     * @param Generator<int, object, mixed, WorkflowState> $run
     * @param callable(): list<object> $pendingApprovals returns the actions still awaiting a decision
     * @param callable(Throwable): void $onError
     * @return Generator<int, string>
     */
    public function __invoke(Generator $run, callable $pendingApprovals, callable $onError): Generator
    {
        // Reasoning models emit <think>…</think> inline; it is never shown to the user.
        $reasoning = new ReasoningFilter();

        try {
            foreach ($run as $chunk) {
                if ($chunk instanceof TextChunk && $chunk->content !== '') {
                    $visible = $reasoning->feed($chunk->content);
                    if ($visible !== '') {
                        yield AgentEvent::encode(AgentEvent::TEXT_DELTA, ['text' => $visible]);
                    }
                } elseif ($chunk instanceof ToolCallChunk) {
                    yield AgentEvent::encode(AgentEvent::TOOL_STARTED, [
                        'id' => $chunk->tool->getCallId(),
                        'tool' => $chunk->tool->getName(),
                        'inputs' => $chunk->tool->getInputs(),
                    ]);
                } elseif ($chunk instanceof ToolResultChunk) {
                    $raw = $chunk->tool->getResult();
                    yield AgentEvent::encode(AgentEvent::TOOL_FINISHED, [
                        'id' => $chunk->tool->getCallId(),
                        'tool' => $chunk->tool->getName(),
                    ] + ToolOutcome::fromRaw(is_string($raw) ? $raw : ''));
                }
            }

            $tail = $reasoning->flush();
            if ($tail !== '') {
                yield AgentEvent::encode(AgentEvent::TEXT_DELTA, ['text' => $tail]);
            }

            $state = $run->getReturn();
            $interrupted = $state->isInterrupted();

            if ($interrupted) {
                foreach ($pendingApprovals() as $action) {
                    yield AgentEvent::encode(AgentEvent::APPROVAL_REQUESTED, [
                        'id' => $action->id,
                        'tool' => $action->name,
                        'reason' => (string) ($action->reason ?? $action->description ?? ''),
                        'inputs' => $action->inputs,
                    ]);
                }
            }

            yield AgentEvent::encode(AgentEvent::RUN_COMPLETED, ['interrupted' => $interrupted]);
        } catch (Throwable $e) {
            $onError($e);
            yield AgentEvent::encode(AgentEvent::RUN_ERROR, ['code' => 'service_error', 'message' => self::ERROR_MESSAGE]);
        }
    }
}
