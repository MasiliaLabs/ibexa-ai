<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Agent\Stream;

use Generator;
use Masilia\IbexaAi\Agent\Stream\AgentEvent;
use Masilia\IbexaAi\Agent\Stream\ToolOutcome;
use Masilia\IbexaAi\Suggestion\ReasoningFilter;
use NeuronAI\Exceptions\RunInFlightException;
use NeuronAI\Exceptions\ToolRunsExceededException;
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
    private const TOO_MANY_STEPS_MESSAGE = 'This request needed too many steps. Ask for a narrower scope, for example one site or one section.';
    private const NO_ANSWER_MESSAGE ='The assistant stopped without answering. Try again, or ask in fewer words.';
    private const IN_FLIGHT_MESSAGE ='The previous answer in this conversation is still being finished. Try again in a few minutes.';

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

        // Whether the model produced anything the user can see or act on (text or a tool call).
        $produced = false;

        try {
            foreach ($run as $chunk) {
                // A reasoning model can think for a long time before any visible text: keep the connection
                // active (an SSE comment, ignored by the client) so no proxy closes the stream meanwhile.
                yield AgentEvent::keepAlive();
                if ($chunk instanceof TextChunk && $chunk->content !== '') {
                    $visible = $reasoning->feed($chunk->content);
                    if ($visible !== '') {
                        $produced = true;
                        yield AgentEvent::encode(AgentEvent::TEXT_DELTA, ['text' => $visible]);
                    }
                } elseif ($chunk instanceof ToolCallChunk) {
                    // A tool call ends the model's message. MiniMax does not close <think> when its message ends
                    // in a tool call, so reasoning state must not carry over: the next message starts clean.
                    $reasoning = new ReasoningFilter();
                    $produced = true;
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
                $produced = true;
                yield AgentEvent::encode(AgentEvent::TEXT_DELTA, ['text' => $tail]);
            }

            $state = $run->getReturn();
            $interrupted = $state->isInterrupted();

            // The model ended its turn with reasoning only (no answer, no tool call): say so, rather than
            // showing an empty reply. The user can rephrase.
            if (!$produced && !$interrupted) {
                yield AgentEvent::encode(AgentEvent::RUN_ERROR, ['code' => 'no_answer', 'message' => self::NO_ANSWER_MESSAGE]);

                return;
            }

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
        } catch (RunInFlightException $e) {
            // An earlier run of this conversation has not finished (its connection dropped): it keeps its lease
            // until it expires. That is a state the user can act on, so it is not reported as an outage.
            $onError($e);
            yield AgentEvent::encode(AgentEvent::RUN_ERROR, ['code' => 'run_in_flight', 'message' => self::IN_FLIGHT_MESSAGE]);
        } catch (ToolRunsExceededException $e) {
            // The model kept calling a tool past its budget and past the warning it was given.
            $onError($e);
            yield AgentEvent::encode(AgentEvent::RUN_ERROR, ['code' => 'too_many_steps', 'message' => self::TOO_MANY_STEPS_MESSAGE]);
        } catch (Throwable $e) {
            $onError($e);
            yield AgentEvent::encode(AgentEvent::RUN_ERROR, ['code' => 'service_error', 'message' => self::ERROR_MESSAGE]);
        }
    }
}
