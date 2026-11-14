<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Chat;

use Generator;
use Masilia\Bundle\IbexaAi\Chat\Attachment\UserMessageBuilder;
use Masilia\Bundle\IbexaAi\Os\Neuron\OsAgentFactory;
use Masilia\Bundle\IbexaAi\Chat\Stream\AgUiFrames;
use Masilia\Bundle\IbexaAi\Chat\Stream\AlreadyDecidedException;
use Masilia\Bundle\IbexaAi\Chat\Stream\DecisionPendingException;
use Masilia\Bundle\IbexaAi\Chat\Stream\ReasoningFilteredAGUIAdapter;
use Masilia\Bundle\IbexaAi\Chat\Thread\AgentThreads;
use Masilia\Bundle\IbexaAi\Chat\Thread\ThreadAccessDeniedException;
use Masilia\Bundle\IbexaAi\Chat\Thread\ThreadId;
use Masilia\Bundle\IbexaAi\Os\Audit\RunContext;
use Masilia\IbexaAi\Api\ApiError;
use Masilia\Contracts\IbexaAi\Provider\ProviderConfigurationException;
use NeuronAI\Agent\Frontend\AGUIInputTranslator;
use NeuronAI\Exceptions\InputTranslationException;
use NeuronAI\Workflow\Streaming\ProtocolEvent;
use NeuronAI\Workflow\Streaming\SSEEncoder;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * One run of the content agent, over the AG-UI protocol: a new user message, or the decisions on the change that awaits
 * one. It checks the request, claims the conversation for the user, builds the agent, and gives back the frames to send.
 * It knows nothing of HTTP: the controller turns a refusal into a response and the frames into a stream.
 *
 * The conversation is the server's: the thread belongs to the current user and siteaccess, the client only sends its last
 * message or its decision.
 */
final readonly class ChatRun
{
    /** What one message can carry, attached documents included. */
    public const MAX_MESSAGE = 400000;

    public const GENERIC_ERROR = 'The AI agent is currently unavailable. Please try again later or contact an administrator.';

    public function __construct(
        private OsAgentFactory $agents,
        private AgentThreads $threads,
        private UserMessageBuilder $messages,
        private RunContext $runContext,
        private LoggerInterface $aiLogger,
    ) {
    }

    /**
     * @param array<string, mixed> $input A RunAgentInput: threadId, messages, state, context, and `resume` for decisions
     *
     * @throws RunRefused When the request is not acceptable: no thread, a message too long, a thread of someone else, a
     *                    conversation the server cannot read, or nothing to say
     */
    public function start(array $input, int $userId, string $siteaccess): StartedRun
    {
        $threadId = $input['threadId'] ?? null;
        if (!ThreadId::isValid($threadId)) {
            throw RunRefused::invalid('Missing or invalid threadId');
        }
        $message = self::lastUserText($input);
        if (mb_strlen($message) > self::MAX_MESSAGE) {
            throw new RunRefused(ApiError::validation('The message is too long.'), Response::HTTP_REQUEST_ENTITY_TOO_LARGE);
        }
        try {
            $this->threads->claim($threadId, $userId, $siteaccess, $message);
        } catch (ThreadAccessDeniedException) {
            throw new RunRefused(ApiError::accessDenied(), Response::HTTP_FORBIDDEN);
        }

        try {
            $adapter = new ReasoningFilteredAGUIAdapter(
                threadId: $threadId,
                runId: isset($input['runId']) ? (string) $input['runId'] : null,
                messages: is_array($input['messages'] ?? null) ? array_values($input['messages']) : [],
                state: is_array($input['state'] ?? null) ? $input['state'] : [],
            );
        } catch (InputTranslationException $e) {
            $this->aiLogger->info('[AI Agent] AG-UI input refused: {message}', ['message' => $e->getMessage()]);

            throw RunRefused::invalid('The conversation sent by the client is not valid.');
        }
        try {
            $agent = $this->agents->create($siteaccess, $threadId);
            $agent->setStreamAdapter(static fn () => $adapter);
            $this->runContext->begin($threadId, $siteaccess);
            $agent->withPageContext(PageContext::fromAgUiContext($input['context'] ?? null));
            $vision = $this->agents->supportsVision($siteaccess);
            $agent->withVision($vision);

            if (is_array($input['resume'] ?? null) && $input['resume'] !== []) {
                try {
                    $events = $agent->submitInputs($input, new AGUIInputTranslator())->events();
                } catch (InputTranslationException $e) {
                    // A decision for a run that is no longer waiting: decided in another window.
                    $this->aiLogger->info('[AI Agent] AG-UI decision refused: {message}', ['message' => $e->getMessage()]);
                    throw new AlreadyDecidedException($e->getMessage(), 0, $e);
                }
            } else {
                if ($message === '') {
                    throw RunRefused::invalid('Missing required field: a user message or resume');
                }
                // A suspended run cannot take a new message: the pending change must be decided first.
                if ($agent->pendingApprovals() !== []) {
                    throw new DecisionPendingException('A change is waiting for a decision.');
                }
                $events = $agent->stream($this->messages->build($message, $vision, $userId));
            }
        } catch (RunRefused $refused) {
            throw $refused;
        } catch (Throwable $e) {
            if (!$e instanceof AlreadyDecidedException && !$e instanceof DecisionPendingException && !$e instanceof ProviderConfigurationException) {
                $this->aiLogger->error('[AI Agent] AG-UI run could not start: {message}', ['message' => $e->getMessage(), 'exception' => $e]);
            }

            return new StartedRun(self::headers($adapter), self::failed($adapter, $e));
        }

        return new StartedRun(self::headers($adapter), $this->stream($events));
    }

    /**
     * The headers of the stream: the adapter's, and none that lets a proxy hold the frames back.
     *
     * @return array<string, string>
     */
    private static function headers(ReasoningFilteredAGUIAdapter $adapter): array
    {
        return $adapter->getHeaders() + ['X-Accel-Buffering' => 'no'];
    }

    /**
     * The frames of a run. A run that fails on the way ends with an error event, unless it already closed itself: the
     * adapter closes the run for the failures it sees (and the exception then reaches here too), and a second terminal
     * event would break the protocol.
     *
     * @param Generator<int, ProtocolEvent, mixed, mixed> $events
     *
     * @return Generator<int, string>
     */
    public function stream(Generator $events): Generator
    {
        $closed = false;
        try {
            foreach (AgUiFrames::encode($events) as $frame) {
                yield $frame;
                $closed = $closed || str_contains($frame, '"type":"RUN_ERROR"') || str_contains($frame, '"type":"RUN_FINISHED"');
            }
        } catch (Throwable $e) {
            $this->aiLogger->error('[AI Agent] AG-UI run failed: {message}', ['message' => $e->getMessage(), 'exception' => $e]);
            if (!$closed) {
                yield SSEEncoder::frame(new ProtocolEvent('RUN_ERROR', ['message' => self::GENERIC_ERROR, 'code' => 'service_error']));
            }
        }
    }

    /**
     * A run that cannot start, told the way AG-UI tells any failed run, so the client shows the message as it shows every
     * other one.
     *
     * @return Generator<int, string>
     */
    private static function failed(ReasoningFilteredAGUIAdapter $adapter, Throwable $failure): Generator
    {
        foreach ([...$adapter->start(), ...$adapter->error($failure)] as $event) {
            yield SSEEncoder::frame($event);
        }
    }

    /**
     * The text of the last message of the user: what to run, and what titles a new conversation.
     *
     * @param array<string, mixed> $input
     */
    private static function lastUserText(array $input): string
    {
        $messages = is_array($input['messages'] ?? null) ? $input['messages'] : [];
        for ($i = count($messages) - 1; $i >= 0; --$i) {
            $message = $messages[$i];
            if (!is_array($message) || ($message['role'] ?? null) !== 'user') {
                continue;
            }
            $content = $message['content'] ?? '';
            if (is_string($content)) {
                return trim($content);
            }
            $text = '';
            foreach (is_array($content) ? $content : [] as $part) {
                if (is_array($part) && ($part['type'] ?? null) === 'text' && is_string($part['text'] ?? null)) {
                    $text .= $part['text'];
                }
            }

            return trim($text);
        }

        return '';
    }
}
