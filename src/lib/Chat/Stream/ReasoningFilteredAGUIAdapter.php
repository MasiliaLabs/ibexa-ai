<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Chat\Stream;

use Masilia\Contracts\IbexaAi\Provider\ProviderConfigurationException;
use Masilia\Contracts\IbexaAi\Llm\ReasoningFilter;
use NeuronAI\Agent\Adapters\AGUIAdapter;
use NeuronAI\Agent\Interrupt\ApprovalRequest;
use NeuronAI\Chat\Messages\Stream\Chunks\TextChunk;
use NeuronAI\Chat\Messages\Stream\Chunks\ToolCallChunk;
use NeuronAI\Chat\Messages\Stream\Chunks\ToolResultChunk;
use NeuronAI\Exceptions\RunInFlightException;
use NeuronAI\Exceptions\ToolRunsExceededException;
use NeuronAI\Tools\ToolCall;
use NeuronAI\Workflow\Interrupt\InterruptRequest;
use NeuronAI\Workflow\Streaming\ProtocolEvent;
use Throwable;

/**
 * Neuron's AG-UI adapter, completed for the admin assistant:
 *
 * - the reasoning of models that write it inline (<think>…</think>) is not streamed;
 * - while a model thinks and writes nothing visible, a {@see self::KEEP_ALIVE} event is produced, which the HTTP
 *   edge turns into an SSE comment so that no proxy closes a stream that is only silent;
 * - the approval of a tool call is an AG-UI `tool_call` interrupt that names the call, and the call is published
 *   before it (clients show the approval on the call);
 * - a run that ends without anything to show is an error, not an empty answer;
 * - failures say what the user can do about them instead of a neutral text.
 */
final class ReasoningFilteredAGUIAdapter extends AGUIAdapter
{
    /** Not an AG-UI event: the HTTP edge writes it as an SSE comment. */
    public const KEEP_ALIVE = 'masilia.keep-alive';

    private const GENERIC_ERROR = 'The AI agent is currently unavailable. Please try again later or contact an administrator.';
    private const TOO_MANY_STEPS = 'This request needed too many steps. Ask for a narrower scope, for example one site or one section.';
    private const NO_ANSWER = 'The assistant stopped without answering. Try again, or ask in fewer words.';
    private const ALREADY_DECIDED = 'This change was already decided, probably in another window. The conversation was refreshed.';
    private const DECISION_PENDING = 'A change is waiting for your decision. Approve or refuse it first.';
    private const IN_FLIGHT = 'The previous answer in this conversation is still being finished. Try again in a few minutes.';

    private ReasoningFilter $reasoning;

    /** Whether the model produced anything the user can see or act on (text or a tool call). */
    private bool $produced = false;

    /**
     * @param list<array<string, mixed>> $messages
     * @param array<string, mixed> $state
     */
    public function __construct(string $threadId, ?string $runId = null, array $messages = [], array $state = [])
    {
        parent::__construct($threadId, $runId, $messages, $state);
        $this->reasoning = new ReasoningFilter();
    }

    public function reset(): void
    {
        parent::reset();
        $this->reasoning = new ReasoningFilter();
        $this->produced = false;
    }

    public function transform(object $chunk): iterable
    {
        if ($chunk instanceof TextChunk) {
            $visible = $this->reasoning->feed($chunk->content);
            if ($visible === '') {
                // Thinking, not writing: nothing to show, but the connection must stay active.
                return [new ProtocolEvent(self::KEEP_ALIVE)];
            }
            $this->produced = true;
            $chunk = new TextChunk($chunk->messageId, $visible);
        } elseif ($chunk instanceof ToolCallChunk) {
            // MiniMax does not close <think> when its message ends in a tool call: the next message starts clean.
            $this->reasoning = new ReasoningFilter();
            $this->produced = true;
        } elseif ($chunk instanceof ToolResultChunk) {
            $this->produced = true;
        }

        return parent::transform($chunk);
    }

    /**
     * A run that ends with nothing to show (the model only reasoned) is reported rather than shown as an empty
     * answer; the user can rephrase.
     */
    public function end(): iterable
    {
        if (!$this->runFailed && !$this->finished && !$this->produced) {
            yield from $this->error(new NoAnswerException());

            return;
        }

        yield from parent::end();
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

    /**
     * What the user sees. Exception messages carry internals (provider URLs, paths), so each known failure has
     * its own text and everything else is neutral; the details are in the log.
     */
    protected function errorMessage(Throwable $error): string
    {
        return match (true) {
            $error instanceof NoAnswerException => self::NO_ANSWER,
            $error instanceof AlreadyDecidedException => self::ALREADY_DECIDED,
            $error instanceof DecisionPendingException => self::DECISION_PENDING,
            $error instanceof RunInFlightException => self::IN_FLIGHT,
            $error instanceof ToolRunsExceededException => self::TOO_MANY_STEPS,
            $error instanceof ProviderConfigurationException => $error->getMessage(),
            default => self::GENERIC_ERROR,
        };
    }
}
