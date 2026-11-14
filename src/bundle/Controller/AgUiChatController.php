<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Controller;

use Ibexa\Contracts\AdminUi\Controller\Controller;
use Ibexa\Contracts\Core\Repository\PermissionResolver;
use Ibexa\Core\MVC\Symfony\Security\Authorization\Attribute;
use Ibexa\Core\MVC\Symfony\SiteAccess\SiteAccessServiceInterface;
use Masilia\Contracts\IbexaAi\Http\CsrfProtectedController;
use Masilia\IbexaAi\Os\Neuron\OsAgentFactory;
use Masilia\IbexaAi\Chat\ChatRun;
use Masilia\IbexaAi\Chat\Refusal;
use Masilia\IbexaAi\Chat\RunRefused;
use Masilia\IbexaAi\Chat\Attachment\AttachmentRejectedException;
use Masilia\IbexaAi\Chat\Attachment\AttachmentStore;
use Masilia\IbexaAi\Chat\Mention\ContentMentions;
use Masilia\IbexaAi\Chat\Stream\ReasoningFilteredAGUIAdapter;
use Masilia\IbexaAi\Chat\Thread\AgentThreads;
use Masilia\IbexaAi\Chat\Thread\ThreadId;
use Masilia\IbexaAi\Chat\Thread\ThreadTitle;
use Masilia\Contracts\IbexaAi\Api\ApiError;
use Masilia\Contracts\IbexaAi\Provider\ProviderConfigurationException;
use Masilia\IbexaAi\Llm\ReasoningFilter;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

/**
 * The content agent over the AG-UI protocol (https://docs.ag-ui.com), for AG-UI clients such as assistant-ui.
 *
 * POST /api/ai/agent/agui        a RunAgentInput: a new user message, or `resume` entries answering the
 *                                      change that awaits a decision. Streams AG-UI events.
 * GET  /api/ai/agent/agui/state  the stored conversation and its pending interrupts, to restore a page.
 * DELETE /api/ai/agent/conversation  forgets the conversation of the current user.
 *
 * The conversation is the server's: the thread belongs to the current user and siteaccess, the client only
 * sends its last message or its decision.
 */
final class AgUiChatController extends Controller implements CsrfProtectedController
{
    public function __construct(
        private readonly PermissionResolver $permissions,
        private readonly SiteAccessServiceInterface $siteAccess,
        private readonly OsAgentFactory $agents,
        private readonly AgentThreads $threads,
        private readonly ContentMentions $mentions,
        private readonly AttachmentStore $attachments,
        private readonly ChatRun $chat,
        private readonly LoggerInterface $aiLogger,
    ) {
    }

    /**
     * The chat is for whoever the role lets use the assistant (masilia_ai/use). Each capability then asks for its own rights, and runs
     * with those of the user: an editor only changes what the role lets them change, and what changes is shown to them first.
     */
    public function performAccessCheck(): void
    {
        parent::performAccessCheck();
        $this->denyAccessUnlessGranted(new Attribute('masilia_ai', 'use'));
    }

    #[Route('/api/ai/agent/agui', name: 'masilia.ibexa_ai.agent.agui', methods: ['POST'], options: ['expose' => true])]
    public function run(Request $request): Response
    {
        $input = $request->getPayload()->all();

        try {
            $run = $this->chat->start($input, $this->userId(), $this->siteaccess());
        } catch (RunRefused $refused) {
            return new JsonResponse($refused->error->toArray(), match ($refused->reason) {
                Refusal::Invalid => Response::HTTP_BAD_REQUEST,
                Refusal::TooLong => Response::HTTP_REQUEST_ENTITY_TOO_LARGE,
                Refusal::Forbidden => Response::HTTP_FORBIDDEN,
            });
        }

        return new StreamedResponse(static function () use ($run): void {
            foreach ($run->frames as $frame) {
                echo $frame;
                flush();
            }
        }, Response::HTTP_OK, $run->headers);
    }

    #[Route('/api/ai/agent/agui/state', name: 'masilia.ibexa_ai.agent.agui_state', methods: ['GET'], options: ['expose' => true])]
    public function state(Request $request): JsonResponse
    {
        // Without an id: the conversation the user was last in, if any (the widget opens on it).
        $requested = $request->query->get('threadId');
        if ($requested === null) {
            $latest = $this->threads->latest($this->userId(), $this->siteaccess());
            if ($latest === null) {
                return new JsonResponse(['threadId' => null, 'messages' => [], 'interrupts' => []]);
            }
            $requested = $latest->id;
        }
        if (!ThreadId::isValid($requested) || $this->threads->owned($requested, $this->userId()) === null) {
            return new JsonResponse(ApiError::notFound('Conversation not found')->toArray(), Response::HTTP_NOT_FOUND);
        }

        $threadId = $requested;
        $empty = ['threadId' => $threadId, 'messages' => [], 'interrupts' => []];

        try {
            $agent = $this->agents->create($this->siteaccess(), $threadId);
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
            if (($message['role'] ?? null) === 'user' && is_array($message['content'] ?? null)) {
                // Images sent to the model are not sent back to the view: the text names them.
                $text = '';
                foreach ($message['content'] as $part) {
                    if (is_array($part) && ($part['type'] ?? null) === 'text' && is_string($part['text'] ?? null)) {
                        $text .= $part['text'];
                    }
                }
                $message['content'] = $text;
            }
            if (($message['role'] ?? null) === 'assistant' && is_string($message['content'] ?? null)) {
                $message['content'] = trim(ReasoningFilter::strip($message['content']));
            }
            $messages[] = $message;
        }

        return new JsonResponse(['threadId' => $threadId, 'messages' => $messages, 'interrupts' => $hydrated['interrupts']]);
    }

    #[Route('/api/ai/agent/attachments', name: 'masilia.ibexa_ai.agent.attachment_upload', methods: ['POST'], options: ['expose' => true])]
    public function uploadAttachment(Request $request): JsonResponse
    {
        $file = $request->files->get('file');
        if (!$file instanceof UploadedFile || !$file->isValid()) {
            return new JsonResponse(ApiError::validation('No file was received.')->toArray(), Response::HTTP_BAD_REQUEST);
        }

        try {
            $attachment = $this->attachments->store($file->getPathname(), $file->getClientOriginalName(), $this->userId());
        } catch (AttachmentRejectedException $e) {
            return new JsonResponse(ApiError::validation($e->getMessage())->toArray(), Response::HTTP_BAD_REQUEST);
        }

        return new JsonResponse($attachment->toArray());
    }

    #[Route('/api/ai/agent/attachments/{id}', name: 'masilia.ibexa_ai.agent.attachment', methods: ['GET'], options: ['expose' => true])]
    public function attachment(string $id): Response
    {
        $attachment = AttachmentStore::isValidId($id) ? $this->attachments->find($id, $this->userId()) : null;
        if ($attachment === null || !is_file($this->attachments->path($attachment))) {
            return new JsonResponse(ApiError::notFound('Attachment not found')->toArray(), Response::HTTP_NOT_FOUND);
        }

        $response = new BinaryFileResponse($this->attachments->path($attachment));
        // The type is the one the store found in the file, never one the client claimed.
        $response->headers->set('Content-Type', $attachment->mime);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Cache-Control', 'private, max-age=3600');

        return $response;
    }

    #[Route('/api/ai/agent/mentions', name: 'masilia.ibexa_ai.agent.mentions', methods: ['GET'], options: ['expose' => true])]
    public function mentions(Request $request): JsonResponse
    {
        return new JsonResponse(['contents' => $this->mentions->search((string) $request->query->get('q', ''))]);
    }

    #[Route('/api/ai/agent/threads', name: 'masilia.ibexa_ai.agent.threads', methods: ['GET'], options: ['expose' => true])]
    public function threads(): JsonResponse
    {
        return new JsonResponse([
            'threads' => array_map(
                static fn ($thread): array => $thread->toArray(),
                $this->threads->list($this->userId(), $this->siteaccess()),
            ),
        ]);
    }

    #[Route('/api/ai/agent/threads/{threadId}', name: 'masilia.ibexa_ai.agent.thread_rename', methods: ['PATCH'], options: ['expose' => true])]
    public function renameThread(string $threadId, Request $request): JsonResponse
    {
        $input = $request->getPayload()->all();
        $title = is_string($input['title'] ?? null) ? ThreadTitle::fromUser($input['title']) : null;
        if ($title === null) {
            return new JsonResponse(ApiError::validation('A title is required')->toArray(), Response::HTTP_BAD_REQUEST);
        }
        if (!ThreadId::isValid($threadId) || !$this->threads->rename($threadId, $this->userId(), $title)) {
            return new JsonResponse(ApiError::notFound('Conversation not found')->toArray(), Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse(['id' => $threadId, 'title' => $title]);
    }

    #[Route('/api/ai/agent/threads/{threadId}', name: 'masilia.ibexa_ai.agent.thread_delete', methods: ['DELETE'], options: ['expose' => true])]
    public function deleteThread(string $threadId): JsonResponse
    {
        if (!ThreadId::isValid($threadId) || $this->threads->owned($threadId, $this->userId()) === null) {
            return new JsonResponse(ApiError::notFound('Conversation not found')->toArray(), Response::HTTP_NOT_FOUND);
        }

        $this->agents->messages($threadId)->clear($threadId);
        $this->agents->forgetWorkflow($threadId);
        $this->threads->delete($threadId, $this->userId());

        return new JsonResponse(['success' => true]);
    }

    private function siteaccess(): string
    {
        return $this->siteAccess->getCurrent()->name;
    }

    private function userId(): int
    {
        return (int) $this->permissions->getCurrentUserReference()->getUserId();
    }
}
