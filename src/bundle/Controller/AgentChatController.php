<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Controller;

use Ibexa\Contracts\Core\Repository\PermissionResolver;
use Ibexa\Core\MVC\Symfony\SiteAccess\SiteAccessServiceInterface;
use Masilia\Bundle\IbexaAi\Agent\AgentFactory;
use Masilia\IbexaAi\Api\ApiError;
use Masilia\IbexaAi\Provider\ProviderConfigurationException;
use NeuronAI\Chat\Messages\Stream\Chunks\TextChunk;
use NeuronAI\Chat\Messages\Stream\Chunks\ToolCallChunk;
use NeuronAI\Chat\Messages\Stream\Chunks\ToolResultChunk;
use NeuronAI\Chat\Messages\UserMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
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
    public function chat(Request $request): Response
    {
        $headers = ['Content-Type' => 'text/event-stream', 'Cache-Control' => 'no-cache', 'X-Accel-Buffering' => 'no'];

        if ($this->permissions->hasAccess('setup', 'administrate') === false) {
            return $this->streamError(ApiError::accessDenied(), Response::HTTP_FORBIDDEN, $headers);
        }

        $payload = $this->json($request);
        if ($payload === null) {
            return $this->streamError(ApiError::validation('Invalid JSON payload'), Response::HTTP_BAD_REQUEST, $headers);
        }

        [$message, $decisions] = $this->interpret($payload);
        if ($message === '' && $decisions === null) {
            return $this->streamError(ApiError::validation('Missing required field: message or decisions'), Response::HTTP_BAD_REQUEST, $headers);
        }

        try {
            $agent = $this->agents->create($this->currentSiteaccess(), $this->threadId());
            $run = $decisions !== null
                ? $agent->submitApprovalDecisions($decisions)->events()
                : $agent->stream(new UserMessage($message));
        } catch (ProviderConfigurationException $e) {
            return $this->streamError(ApiError::serviceUnavailable($e->getMessage()), Response::HTTP_SERVICE_UNAVAILABLE, $headers);
        } catch (Throwable $e) {
            $this->aiLogger->error('[AI Agent] Chat failed: {message}', ['message' => $e->getMessage(), 'exception' => $e]);

            return $this->streamError(ApiError::serviceUnavailable(self::GENERIC_ERROR), Response::HTTP_SERVICE_UNAVAILABLE, $headers);
        }

        return new StreamedResponse(function () use ($agent, $run): void {
            $text = '';
            try {
                foreach ($run as $chunk) {
                    if ($chunk instanceof TextChunk) {
                        $text .= $chunk->content;
                    } elseif ($chunk instanceof ToolCallChunk) {
                        $this->emit([
                            'type' => 'step_start',
                            'tool' => $chunk->tool->getName(),
                            'call_id' => $chunk->tool->getCallId(),
                        ]);
                    } elseif ($chunk instanceof ToolResultChunk) {
                        $this->emit([
                            'type' => 'step_result',
                            'tool' => $chunk->tool->getName(),
                            'call_id' => $chunk->tool->getCallId(),
                            'result' => $this->toolResult($chunk->tool->getResult()),
                        ]);
                    }
                }

                $state = $run->getReturn();
            } catch (Throwable $e) {
                $this->aiLogger->error('[AI Agent] Run failed: {message}', ['message' => $e->getMessage(), 'exception' => $e]);
                $this->emit(['type' => 'error', 'error_type' => 'service_error', 'message' => self::GENERIC_ERROR]);
                echo "data: [DONE]\n\n";
                flush();

                return;
            }

            if ($state->isInterrupted()) {
                // Each pending approval is offered as two options; the front sends the chosen value back.
                $pending = $agent->pendingApprovals();
                foreach ($pending as $action) {
                    $this->emit([
                        'type' => 'options',
                        'message' => $this->approvalText($action->name, (string) ($action->reason ?? $action->description ?? '')),
                        'options' => [
                            ['label' => 'Approve', 'value' => 'approve:' . $action->id],
                            ['label' => 'Reject', 'value' => 'reject:' . $action->id],
                        ],
                    ]);
                }
                if ($pending === []) {
                    $this->emit(['type' => 'message', 'message' => 'The change is waiting for your decision.']);
                }
            } else {
                $this->emit(['type' => 'message', 'message' => $text !== '' ? $text : (string) $state->getMessage()?->getContent()]);
            }

            echo "data: [DONE]\n\n";
            flush();
        }, Response::HTTP_OK, $headers);
    }

    #[Route('/admin/api/ai/agent/history', name: 'masilia.ibexa_ai.agent.history', methods: ['GET'])]
    public function history(): JsonResponse
    {
        if ($denied = $this->denyUnlessAdministrator()) {
            return $denied;
        }

        $messages = [];
        foreach ($this->agents->messages($this->threadId())->loadAll($this->threadId(), 200) as $message) {
            $role = $message->getRole()->value;
            if (!in_array($role, ['user', 'assistant'], true) || trim((string) $message->getContent()) === '') {
                continue;
            }
            $messages[] = [
                'role' => $role === 'assistant' ? 'agent' : 'user',
                'content' => (string) $message->getContent(),
                'timestamp' => '',
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
     * @param array<string, mixed> $payload
     * @return array{0: string, 1: array<string, mixed>|null}
     */
    private function interpret(array $payload): array
    {
        $message = trim((string) ($payload['message'] ?? ''));
        $selected = (string) ($payload['selected_option'] ?? '');

        // An option the widget sent back: "approve:<id>" or "reject:<id>".
        if (preg_match('/^(approve|reject):(.+)$/', $selected, $m) === 1) {
            return ['', [$m[2] => $m[1]]];
        }

        $decisions = is_array($payload['decisions'] ?? null) ? $this->normalizeDecisions($payload['decisions']) : null;

        return [$message, $decisions];
    }

    /**
     * @return array<string, mixed>
     */
    private function toolResult(string|\NeuronAI\Tools\ToolOutput $result): array
    {
        $text = is_string($result) ? $result : '';
        $decoded = json_decode($text, true);

        // Neuron answers a rejected or skipped call with its own text, not with the tool's JSON.
        if (str_starts_with($text, 'TOOL NOT EXECUTED')) {
            return ['success' => false, 'message' => 'Not executed: the change was rejected.', 'data' => []];
        }

        return is_array($decoded)
            ? ['success' => (bool) ($decoded['success'] ?? false), 'message' => (string) ($decoded['message'] ?? ''), 'data' => $decoded['data'] ?? []]
            : ['success' => true, 'message' => $text, 'data' => []];
    }

    private function approvalText(string $tool, string $reason): string
    {
        return $reason !== '' ? $reason : sprintf('The assistant wants to run "%s". Approve it?', $tool);
    }

    /**
     * @param array<string, mixed> $event
     */
    private function emit(array $event): void
    {
        echo 'data: ' . json_encode($event, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) . "\n\n";
        flush();
    }

    /**
     * @param array<string, mixed> $headers
     */
    private function streamError(ApiError $error, int $status, array $headers): StreamedResponse
    {
        return new StreamedResponse(function () use ($error): void {
            echo 'data: ' . json_encode($error->toArray(), JSON_THROW_ON_ERROR) . "\n\n";
            flush();
        }, $status, $headers);
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
