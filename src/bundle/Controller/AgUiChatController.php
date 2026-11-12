<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Controller;

use Ibexa\Contracts\Core\Repository\PermissionResolver;
use Ibexa\Core\MVC\Symfony\SiteAccess\SiteAccessServiceInterface;
use Masilia\Bundle\IbexaAi\Agent\AgentFactory;
use Masilia\Bundle\IbexaAi\Agent\Stream\AgUiFrames;
use Masilia\Bundle\IbexaAi\Agent\Stream\AlreadyDecidedException;
use Masilia\Bundle\IbexaAi\Agent\Stream\DecisionPendingException;
use Masilia\Bundle\IbexaAi\Agent\Stream\ReasoningFilteredAGUIAdapter;
use Masilia\IbexaAi\Api\ApiError;
use Masilia\IbexaAi\Provider\ProviderConfigurationException;
use Masilia\IbexaAi\Suggestion\ReasoningFilter;
use NeuronAI\Agent\Frontend\AGUIInputTranslator;
use NeuronAI\Chat\Messages\UserMessage;
use NeuronAI\Exceptions\InputTranslationException;
use NeuronAI\Workflow\Streaming\ProtocolEvent;
use NeuronAI\Workflow\Streaming\SSEEncoder;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

/**
 * The content agent over the AG-UI protocol (https://docs.ag-ui.com), for AG-UI clients such as assistant-ui.
 *
 * POST /admin/api/ai/agent/agui        a RunAgentInput: a new user message, or `resume` entries answering the
 *                                      change that awaits a decision. Streams AG-UI events.
 * GET  /admin/api/ai/agent/agui/state  the stored conversation and its pending interrupts, to restore a page.
 * DELETE /admin/api/ai/agent/conversation  forgets the conversation of the current user.
 *
 * The conversation is the server's: the thread belongs to the current user and siteaccess, the client only
 * sends its last message or its decision.
 */
final readonly class AgUiChatController
{
    private const GENERIC_ERROR = 'The AI agent is currently unavailable. Please try again later or contact an administrator.';

    public function __construct(
        private PermissionResolver $permissions,
        private SiteAccessServiceInterface $siteAccess,
        private AgentFactory $agents,
        private LoggerInterface $aiLogger,
    ) {
    }

    #[Route('/admin/api/ai/agent/agui', name: 'masilia.ibexa_ai.agent.agui', methods: ['POST'])]
    public function run(Request $request): Response
    {
        if (!$this->permissions->hasAccess('setup', 'administrate')) {
            return new JsonResponse(ApiError::accessDenied()->toArray(), Response::HTTP_FORBIDDEN);
        }

        $input = $this->json($request);
        if ($input === null) {
            return new JsonResponse(ApiError::validation('Invalid JSON payload')->toArray(), Response::HTTP_BAD_REQUEST);
        }

        try {
            $adapter = new ReasoningFilteredAGUIAdapter(
                threadId: (string) ($input['threadId'] ?? 'agent'),
                runId: isset($input['runId']) ? (string) $input['runId'] : null,
                messages: is_array($input['messages'] ?? null) ? array_values($input['messages']) : [],
                state: is_array($input['state'] ?? null) ? $input['state'] : [],
            );
        } catch (InputTranslationException $e) {
            $this->aiLogger->info('[AI Agent] AG-UI input refused: {message}', ['message' => $e->getMessage()]);

            return new JsonResponse(ApiError::validation('The conversation sent by the client is not valid.')->toArray(), Response::HTTP_BAD_REQUEST);
        }

        try {
            $agent = $this->agents->create($this->currentSiteaccess(), $this->threadId());
            $agent->setStreamAdapter(static fn () => $adapter);

            if (is_array($input['resume'] ?? null) && $input['resume'] !== []) {
                try {
                    $events = $agent->submitInputs($input, new AGUIInputTranslator())->events();
                } catch (InputTranslationException $e) {
                    // A decision for a run that is no longer waiting: decided in another window.
                    $this->aiLogger->info('[AI Agent] AG-UI decision refused: {message}', ['message' => $e->getMessage()]);
                    throw new AlreadyDecidedException($e->getMessage(), 0, $e);
                }
            } else {
                $text = $this->lastUserText($input);
                if ($text === '') {
                    return new JsonResponse(ApiError::validation('Missing required field: a user message or resume')->toArray(), Response::HTTP_BAD_REQUEST);
                }
                // A suspended run cannot take a new message: the pending change must be decided first.
                if ($agent->pendingApprovals() !== []) {
                    throw new DecisionPendingException('A change is waiting for a decision.');
                }
                $events = $agent->stream(new UserMessage($text));
            }
        } catch (Throwable $e) {
            if (!$e instanceof AlreadyDecidedException && !$e instanceof DecisionPendingException && !$e instanceof ProviderConfigurationException) {
                $this->aiLogger->error('[AI Agent] AG-UI run could not start: {message}', ['message' => $e->getMessage(), 'exception' => $e]);
            }

            return $this->failedRun($adapter, $e);
        }

        $headers = $adapter->getHeaders() + ['X-Accel-Buffering' => 'no'];

        return new StreamedResponse(function () use ($events): void {
            $closed = false;
            try {
                foreach (AgUiFrames::encode($events) as $frame) {
                    echo $frame;
                    flush();
                    $closed = $closed || str_contains($frame, '"type":"RUN_ERROR"') || str_contains($frame, '"type":"RUN_FINISHED"');
                }
            } catch (Throwable $e) {
                $this->aiLogger->error('[AI Agent] AG-UI run failed: {message}', ['message' => $e->getMessage(), 'exception' => $e]);
                // The adapter closes the run for the failures it sees (and the exception then reaches here too): a
                // second terminal event would break the protocol, so only an unclosed run gets one.
                if (!$closed) {
                    echo SSEEncoder::frame(new ProtocolEvent('RUN_ERROR', ['message' => self::GENERIC_ERROR, 'code' => 'service_error']));
                    flush();
                }
            }
        }, Response::HTTP_OK, $headers);
    }

    /**
     * A run that cannot start, told the way AG-UI tells any failed run, so the client shows the message as it
     * shows every other one.
     */
    private function failedRun(ReasoningFilteredAGUIAdapter $adapter, Throwable $failure): StreamedResponse
    {
        return new StreamedResponse(static function () use ($adapter, $failure): void {
            foreach ([...$adapter->start(), ...$adapter->error($failure)] as $event) {
                echo SSEEncoder::frame($event);
                flush();
            }
        }, Response::HTTP_OK, $adapter->getHeaders() + ['X-Accel-Buffering' => 'no']);
    }

    #[Route('/admin/api/ai/agent/agui/state', name: 'masilia.ibexa_ai.agent.agui_state', methods: ['GET'])]
    public function state(): JsonResponse
    {
        if (!$this->permissions->hasAccess('setup', 'administrate')) {
            return new JsonResponse(ApiError::accessDenied()->toArray(), Response::HTTP_FORBIDDEN);
        }

        $threadId = $this->threadId();
        $empty = ['threadId' => $threadId, 'messages' => [], 'interrupts' => []];

        try {
            $agent = $this->agents->create($this->currentSiteaccess(), $threadId);
            $stored = iterator_to_array($this->agents->messages($threadId)->loadAll($threadId, 200), false);
            $hydrated = (new ReasoningFilteredAGUIAdapter($threadId))->hydrate($stored, $agent->inspect());
        } catch (ProviderConfigurationException) {
            // No provider configured: nothing to restore.
            return new JsonResponse($empty);
        } catch (Throwable $e) {
            $this->aiLogger->error('[AI Agent] AG-UI state could not be rebuilt: {message}', ['message' => $e->getMessage(), 'exception' => $e]);

            return new JsonResponse($empty);
        }

        // Reasoning written inline by the model is stored with the answer: it is never shown.
        $messages = [];
        foreach ($hydrated['messages'] as $message) {
            if (($message['role'] ?? null) === 'assistant' && is_string($message['content'] ?? null)) {
                $message['content'] = trim(ReasoningFilter::strip($message['content']));
            }
            $messages[] = $message;
        }

        return new JsonResponse(['threadId' => $threadId, 'messages' => $messages, 'interrupts' => $hydrated['interrupts']]);
    }

    #[Route('/admin/api/ai/agent/conversation', name: 'masilia.ibexa_ai.agent.clear', methods: ['DELETE'])]
    public function clear(): JsonResponse
    {
        if (!$this->permissions->hasAccess('setup', 'administrate')) {
            return new JsonResponse(ApiError::accessDenied()->toArray(), Response::HTTP_FORBIDDEN);
        }

        $threadId = $this->threadId();
        $this->agents->messages($threadId)->clear($threadId);
        $this->agents->forgetWorkflow($threadId);

        return new JsonResponse(['success' => true]);
    }

    /**
     * @param array<string, mixed> $input
     */
    private function lastUserText(array $input): string
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

    private function threadId(): string
    {
        return sprintf('agent-%d-%s', $this->permissions->getCurrentUserReference()->getUserId(), $this->currentSiteaccess());
    }

    private function currentSiteaccess(): string
    {
        return $this->siteAccess->getCurrent()?->name ?? 'default';
    }

    /**
     * @return array<string, mixed>|null
     */
    private function json(Request $request): ?array
    {
        try {
            $decoded = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }
}
