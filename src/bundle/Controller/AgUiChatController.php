<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Controller;

use Masilia\Bundle\IbexaAi\Http\ApiRequest;
use Masilia\Bundle\IbexaAi\Security\Access;
use Masilia\Bundle\IbexaAi\Security\RequiresAccess;
use Masilia\Bundle\IbexaAi\Agent\AgentFactory;
use Masilia\Bundle\IbexaAi\Agent\ChatRun;
use Masilia\Bundle\IbexaAi\Agent\RunRefused;
use Masilia\Bundle\IbexaAi\Agent\Attachment\AttachmentRejectedException;
use Masilia\Bundle\IbexaAi\Agent\Attachment\AttachmentStore;
use Masilia\Bundle\IbexaAi\Agent\Mention\ContentMentions;
use Masilia\Bundle\IbexaAi\Agent\Stream\ReasoningFilteredAGUIAdapter;
use Masilia\Bundle\IbexaAi\Agent\Thread\AgentThreads;
use Masilia\Bundle\IbexaAi\Agent\Thread\ThreadId;
use Masilia\Bundle\IbexaAi\Agent\Thread\ThreadTitle;
use Masilia\IbexaAi\Api\ApiError;
use Masilia\IbexaAi\Provider\ProviderConfigurationException;
use Masilia\IbexaAi\Suggestion\ReasoningFilter;
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
 * POST /admin/api/ai/agent/agui        a RunAgentInput: a new user message, or `resume` entries answering the
 *                                      change that awaits a decision. Streams AG-UI events.
 * GET  /admin/api/ai/agent/agui/state  the stored conversation and its pending interrupts, to restore a page.
 * DELETE /admin/api/ai/agent/conversation  forgets the conversation of the current user.
 *
 * The conversation is the server's: the thread belongs to the current user and siteaccess, the client only
 * sends its last message or its decision.
 */
#[RequiresAccess(Access::Administrator)]
final readonly class AgUiChatController
{
    public function __construct(
        private ApiRequest $api,
        private AgentFactory $agents,
        private AgentThreads $threads,
        private ContentMentions $mentions,
        private AttachmentStore $attachments,
        private ChatRun $chat,
        private LoggerInterface $aiLogger,
    ) {
    }

    #[Route('/admin/api/ai/agent/agui', name: 'masilia.ibexa_ai.agent.agui', methods: ['POST'])]
    public function run(Request $request): Response
    {
        $input = $this->api->json($request);
        if ($input === null) {
            return new JsonResponse(ApiError::validation('Invalid JSON payload')->toArray(), Response::HTTP_BAD_REQUEST);
        }

        try {
            $run = $this->chat->start($input, $this->api->userId(), $this->api->siteaccess());
        } catch (RunRefused $refused) {
            return new JsonResponse($refused->error->toArray(), $refused->status);
        }

        return new StreamedResponse(static function () use ($run): void {
            foreach ($run->frames as $frame) {
                echo $frame;
                flush();
            }
        }, Response::HTTP_OK, $run->headers);
    }

    #[Route('/admin/api/ai/agent/agui/state', name: 'masilia.ibexa_ai.agent.agui_state', methods: ['GET'])]
    public function state(Request $request): JsonResponse
    {
        // Without an id: the conversation the user was last in, if any (the widget opens on it).
        $requested = $request->query->get('threadId');
        if ($requested === null) {
            $latest = $this->threads->latest($this->api->userId(), $this->api->siteaccess());
            if ($latest === null) {
                return new JsonResponse(['threadId' => null, 'messages' => [], 'interrupts' => []]);
            }
            $requested = $latest->id;
        }
        if (!ThreadId::isValid($requested) || $this->threads->owned($requested, $this->api->userId()) === null) {
            return new JsonResponse(ApiError::notFound('Conversation not found')->toArray(), Response::HTTP_NOT_FOUND);
        }

        $threadId = $requested;
        $empty = ['threadId' => $threadId, 'messages' => [], 'interrupts' => []];

        try {
            $agent = $this->agents->create($this->api->siteaccess(), $threadId);
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

    #[Route('/admin/api/ai/agent/attachments', name: 'masilia.ibexa_ai.agent.attachment_upload', methods: ['POST'])]
    public function uploadAttachment(Request $request): JsonResponse
    {
        $file = $request->files->get('file');
        if (!$file instanceof UploadedFile || !$file->isValid()) {
            return new JsonResponse(ApiError::validation('No file was received.')->toArray(), Response::HTTP_BAD_REQUEST);
        }

        try {
            $attachment = $this->attachments->store($file->getPathname(), $file->getClientOriginalName(), $this->api->userId());
        } catch (AttachmentRejectedException $e) {
            return new JsonResponse(ApiError::validation($e->getMessage())->toArray(), Response::HTTP_BAD_REQUEST);
        }

        return new JsonResponse($attachment->toArray());
    }

    #[Route('/admin/api/ai/agent/attachments/{id}', name: 'masilia.ibexa_ai.agent.attachment', methods: ['GET'])]
    public function attachment(string $id): Response
    {
        $attachment = AttachmentStore::isValidId($id) ? $this->attachments->find($id, $this->api->userId()) : null;
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

    #[Route('/admin/api/ai/agent/mentions', name: 'masilia.ibexa_ai.agent.mentions', methods: ['GET'])]
    public function mentions(Request $request): JsonResponse
    {
        return new JsonResponse(['contents' => $this->mentions->search((string) $request->query->get('q', ''))]);
    }

    #[Route('/admin/api/ai/agent/threads', name: 'masilia.ibexa_ai.agent.threads', methods: ['GET'])]
    public function threads(): JsonResponse
    {
        return new JsonResponse([
            'threads' => array_map(
                static fn ($thread): array => $thread->toArray(),
                $this->threads->list($this->api->userId(), $this->api->siteaccess()),
            ),
        ]);
    }

    #[Route('/admin/api/ai/agent/threads/{threadId}', name: 'masilia.ibexa_ai.agent.thread_rename', methods: ['PATCH'])]
    public function renameThread(string $threadId, Request $request): JsonResponse
    {
        $input = $this->api->json($request);
        $title = is_array($input) && is_string($input['title'] ?? null) ? ThreadTitle::fromUser($input['title']) : null;
        if ($title === null) {
            return new JsonResponse(ApiError::validation('A title is required')->toArray(), Response::HTTP_BAD_REQUEST);
        }
        if (!ThreadId::isValid($threadId) || !$this->threads->rename($threadId, $this->api->userId(), $title)) {
            return new JsonResponse(ApiError::notFound('Conversation not found')->toArray(), Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse(['id' => $threadId, 'title' => $title]);
    }

    #[Route('/admin/api/ai/agent/threads/{threadId}', name: 'masilia.ibexa_ai.agent.thread_delete', methods: ['DELETE'])]
    public function deleteThread(string $threadId): JsonResponse
    {
        if (!ThreadId::isValid($threadId) || $this->threads->owned($threadId, $this->api->userId()) === null) {
            return new JsonResponse(ApiError::notFound('Conversation not found')->toArray(), Response::HTTP_NOT_FOUND);
        }

        $this->agents->messages($threadId)->clear($threadId);
        $this->agents->forgetWorkflow($threadId);
        $this->threads->delete($threadId, $this->api->userId());

        return new JsonResponse(['success' => true]);
    }
}
