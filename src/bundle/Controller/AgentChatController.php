<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Controller;

use Ibexa\Contracts\Core\Repository\PermissionResolver;
use Ibexa\Core\MVC\Symfony\SiteAccess\SiteAccessServiceInterface;
use Masilia\Bundle\IbexaAi\Agent\AgentFactory;
use Masilia\Bundle\IbexaAi\Agent\Stream\NeuronRunEvents;
use Masilia\IbexaAi\Agent\Stream\AgentEvent;
use Masilia\IbexaAi\Api\ApiError;
use Masilia\IbexaAi\Provider\ProviderConfigurationException;
use Masilia\IbexaAi\Suggestion\ReasoningFilter;
use NeuronAI\Chat\Messages\UserMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

/**
 * The content agent's API, used by the floating assistant in the admin.
 *
 * POST   /admin/api/ai/agent/runs          {"message": "..."} or {"decisions": {"<id>": "approve" | "reject" | ["reject", "reason"]}}
 *                                          streams agent events (see {@see AgentEvent})
 * GET    /admin/api/ai/agent/conversation  messages and changes awaiting a decision, to restore the view after a reload
 * DELETE /admin/api/ai/agent/conversation  forgets the conversation of the current user
 */
final readonly class AgentChatController
{
    private const GENERIC_ERROR = 'The AI agent is currently unavailable. Please try again later or contact an administrator.';

    public function __construct(
        private PermissionResolver $permissions,
        private SiteAccessServiceInterface $siteAccess,
        private AgentFactory $agents,
        private NeuronRunEvents $events,
        private LoggerInterface $aiLogger,
    ) {
    }

    #[Route('/admin/api/ai/agent/runs', name: 'masilia.ibexa_ai.agent.runs', methods: ['POST'])]
    public function run(Request $request): Response
    {
        $headers = ['Content-Type' => 'text/event-stream', 'Cache-Control' => 'no-cache', 'X-Accel-Buffering' => 'no'];

        if (!$this->permissions->hasAccess('setup', 'administrate')) {
            return $this->streamError(ApiError::accessDenied(), Response::HTTP_FORBIDDEN, $headers);
        }

        $payload = $this->json($request);
        if ($payload === null) {
            return $this->streamError(ApiError::validation('Invalid JSON payload'), Response::HTTP_BAD_REQUEST, $headers);
        }

        $message = trim((string) ($payload['message'] ?? ''));
        $decisions = is_array($payload['decisions'] ?? null) ? $this->normalizeDecisions($payload['decisions']) : null;
        if ($message === '' && ($decisions === null || $decisions === [])) {
            return $this->streamError(ApiError::validation('Missing required field: message or decisions'), Response::HTTP_BAD_REQUEST, $headers);
        }

        try {
            $agent = $this->agents->create($this->currentSiteaccess(), $this->threadId());
            // A suspended run cannot take a new message: the pending change must be decided first.
            if ($decisions === null && $agent->pendingApprovals() !== []) {
                return $this->streamError(
                    ApiError::validation('A change is waiting for your decision. Approve or refuse it first.'),
                    Response::HTTP_CONFLICT,
                    $headers,
                );
            }
            $run = $decisions !== null
                ? $agent->submitApprovalDecisions($decisions)->events()
                : $agent->stream(new UserMessage($message));
        } catch (ProviderConfigurationException $e) {
            return $this->streamError(ApiError::serviceUnavailable($e->getMessage()), Response::HTTP_SERVICE_UNAVAILABLE, $headers);
        } catch (Throwable $e) {
            $this->aiLogger->error('[AI Agent] Run could not start: {message}', ['message' => $e->getMessage(), 'exception' => $e]);

            return $this->streamError(ApiError::serviceUnavailable(self::GENERIC_ERROR), Response::HTTP_SERVICE_UNAVAILABLE, $headers);
        }

        $events = ($this->events)(
            $run,
            static fn (): array => $agent->pendingApprovals(),
            function (Throwable $e): void {
                $this->aiLogger->error('[AI Agent] Run failed: {message}', ['message' => $e->getMessage(), 'exception' => $e]);
            },
        );

        return new StreamedResponse(function () use ($events): void {
            foreach ($events as $event) {
                echo $event;
                flush();
            }
        }, Response::HTTP_OK, $headers);
    }

    #[Route('/admin/api/ai/agent/conversation', name: 'masilia.ibexa_ai.agent.conversation', methods: ['GET'])]
    public function conversation(): JsonResponse
    {
        if (!$this->permissions->hasAccess('setup', 'administrate')) {
            return new JsonResponse(ApiError::accessDenied()->toArray(), Response::HTTP_FORBIDDEN);
        }

        $threadId = $this->threadId();
        $messages = [];
        foreach ($this->agents->messages($threadId)->loadAll($threadId, 200) as $index => $message) {
            $roleValue = $message->getRole();
            $role = is_object($roleValue) && property_exists($roleValue, 'value') ? (string) $roleValue->value : (string) $roleValue;
            $text = trim(ReasoningFilter::strip((string) $message->getContent()));
            if (!in_array($role, ['user', 'assistant'], true) || $text === '') {
                continue;
            }
            $messages[] = ['id' => $index, 'role' => $role, 'text' => $text];
        }

        $pending = [];
        try {
            $agent = $this->agents->create($this->currentSiteaccess(), $threadId);
            foreach ($agent->pendingApprovals() as $action) {
                $pending[] = [
                    'id' => $action->id,
                    'tool' => $action->name,
                    'reason' => (string) ($action->reason ?? $action->description ?? ''),
                    'inputs' => $action->inputs,
                ];
            }
        } catch (ProviderConfigurationException) {
            // No provider configured: the history is still shown, nothing can be pending.
        }

        return new JsonResponse(['messages' => $messages, 'pendingApprovals' => $pending]);
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
     * @param array<mixed> $decisions
     * @return array<string, string|array{0: string, 1: string}>
     */
    private function normalizeDecisions(array $decisions): array
    {
        $normalized = [];
        foreach ($decisions as $id => $decision) {
            if (is_string($decision) && in_array($decision, ['approve', 'reject'], true)) {
                $normalized[(string) $id] = $decision;
            } elseif (is_array($decision) && ($decision[0] ?? null) === 'reject') {
                $normalized[(string) $id] = ['reject', (string) ($decision[1] ?? '')];
            }
        }

        return $normalized;
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

    /**
     * @param array<string, string> $headers
     */
    private function streamError(ApiError $error, int $status, array $headers): StreamedResponse
    {
        return new StreamedResponse(static function () use ($error): void {
            echo AgentEvent::encode(AgentEvent::RUN_ERROR, ['code' => $error->code, 'message' => $error->message]);
            flush();
        }, $status, $headers);
    }
}
