<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Controller;

use Ibexa\Contracts\Core\Repository\PermissionResolver;
use Ibexa\Core\MVC\Symfony\SiteAccess\SiteAccessServiceInterface;
use Masilia\Bundle\IbexaAi\Agent\AgentFactory;
use Masilia\IbexaAi\Api\ApiError;
use Masilia\IbexaAi\Provider\ProviderConfigurationException;
use NeuronAI\Chat\Messages\UserMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

/**
 * The content agent's chat API.
 *
 * POST /admin/api/ai/agent/chat takes either {"message": "..."} or {"decisions": {"<approval id>": "approve" | "reject" | ["reject", "reason"]}}.
 * It answers with status "completed" (message) or "awaiting_approval" (approvals to decide).
 */
final readonly class AgentChatController
{
    private const GENERIC_ERROR = 'The AI agent is currently unavailable. Please try again later or contact an administrator.';

    public function __construct(
        private PermissionResolver $permissions,
        private SiteAccessServiceInterface $siteAccess,
        private AgentFactory $agents,
        private LoggerInterface $aiLogger,
    ) {
    }

    #[Route('/admin/api/ai/agent/chat', name: 'masilia.ibexa_ai.agent.chat', methods: ['POST'])]
    public function chat(Request $request): JsonResponse
    {
        if ($denied = $this->denyUnlessAdministrator()) {
            return $denied;
        }

        $payload = $this->json($request);
        if ($payload === null) {
            return $this->error('Invalid JSON payload', Response::HTTP_BAD_REQUEST);
        }

        $message = trim((string) ($payload['message'] ?? ''));
        $decisions = is_array($payload['decisions'] ?? null) ? $payload['decisions'] : null;
        if ($message === '' && $decisions === null) {
            return $this->error('Missing required field: message or decisions', Response::HTTP_BAD_REQUEST);
        }

        try {
            $agent = $this->agents->create($this->currentSiteaccess(), $this->threadId());
            $state = $decisions !== null
                ? $agent->submitApprovalDecisions($this->normalizeDecisions($decisions))->run()
                : $agent->chat(new UserMessage($message));
        } catch (ProviderConfigurationException $e) {
            return $this->error($e->getMessage(), Response::HTTP_SERVICE_UNAVAILABLE);
        } catch (Throwable $e) {
            $this->aiLogger->error('[AI Agent] Chat failed: {message}', ['message' => $e->getMessage(), 'exception' => $e]);

            return new JsonResponse(ApiError::serviceUnavailable(self::GENERIC_ERROR)->toArray(), Response::HTTP_SERVICE_UNAVAILABLE);
        }

        if ($state->isInterrupted()) {
            return new JsonResponse([
                'status' => 'awaiting_approval',
                'approvals' => array_map(
                    static fn ($action): array => [
                        'id' => $action->id,
                        'tool' => $action->name,
                        'inputs' => $action->inputs,
                        'reason' => $action->reason ?? $action->description,
                    ],
                    $agent->pendingApprovals(),
                ),
            ]);
        }

        return new JsonResponse([
            'status' => 'completed',
            'message' => $state->getMessage()?->getContent() ?? '',
        ]);
    }

    #[Route('/admin/api/ai/agent/history', name: 'masilia.ibexa_ai.agent.history', methods: ['GET'])]
    public function history(): JsonResponse
    {
        if ($denied = $this->denyUnlessAdministrator()) {
            return $denied;
        }

        $messages = [];
        foreach ($this->agents->messages($this->threadId())->loadAll($this->threadId(), 200) as $message) {
            $messages[] = [
                'role' => $message->getRole()->value,
                'content' => $message->getContent() ?? '',
            ];
        }

        return new JsonResponse(['messages' => $messages]);
    }

    #[Route('/admin/api/ai/agent/clear', name: 'masilia.ibexa_ai.agent.clear', methods: ['POST'])]
    public function clear(): JsonResponse
    {
        if ($denied = $this->denyUnlessAdministrator()) {
            return $denied;
        }

        $this->agents->messages($this->threadId())->clear($this->threadId());

        return new JsonResponse(['success' => true]);
    }

    /**
     * Decisions arrive as "approve", "reject", or ["reject", "reason"]; Neuron expects the same shapes.
     *
     * @param array<string, mixed> $decisions
     * @return array<string, mixed>
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
        $user = $this->permissions->getCurrentUserReference()->getUserId();

        return sprintf('agent-%d-%s', $user, $this->currentSiteaccess());
    }

    private function currentSiteaccess(): string
    {
        return $this->siteAccess->getCurrent()?->name ?? 'default';
    }

    private function denyUnlessAdministrator(): ?JsonResponse
    {
        if ($this->permissions->hasAccess('setup', 'administrate')) {
            return null;
        }

        return new JsonResponse(ApiError::accessDenied()->toArray(), Response::HTTP_FORBIDDEN);
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

    private function error(string $message, int $status): JsonResponse
    {
        return new JsonResponse(ApiError::validation($message)->toArray(), $status);
    }
}
