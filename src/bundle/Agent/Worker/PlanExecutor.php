<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Agent\Worker;

use Ibexa\Contracts\Core\Repository\PermissionResolver;
use Ibexa\Core\FieldType\Image\Value as ImageValue;
use Masilia\Bundle\IbexaAi\Agent\Attachment\AttachmentStore;
use Ibexa\Contracts\Core\Repository\Exceptions\InvalidArgumentException;
use Ibexa\Contracts\Core\Repository\Exceptions\UnauthorizedException;
use Ibexa\Contracts\Core\Repository\Repository;
use Masilia\Bundle\IbexaAi\Agent\Tool\ContentFactoryInterface;
use Masilia\Bundle\IbexaAi\Agent\Tool\ToolName;
use Masilia\Bundle\IbexaAi\Agent\Tool\ToolRegistry;
use Masilia\Bundle\IbexaAi\Agent\Tool\ToolResult;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Executes a Plan by mapping it to the underlying agent tools.
 *
 * Each plan intent is dispatched to exactly one existing tool call.
 * Failures are returned as structured `ExecutionResult` so the orchestrator
 * can surface them to the user without retrying.
 */
final readonly class PlanExecutor
{
    public function __construct(
        private ToolRegistry             $toolRegistry,
        private LoggerInterface          $aiLogger,
        private ?Repository              $repository = null,
        private ?ContentFactoryInterface $contentFactory = null,
        private ?AttachmentStore $attachments = null,
        private ?PermissionResolver $permissions = null,
    )
    {
    }

    public function execute(Plan $plan, ?callable $progress = null): ExecutionResult
    {
        try {
            return match ($plan->intent) {
                Plan::INTENT_CREATE_CONTENT => $this->createContent($plan),
                Plan::INTENT_UPDATE_CONTENT => $this->updateContent($plan),
                Plan::INTENT_CREATE_FOLDER => $this->createFolder($plan),
                Plan::INTENT_ADD_IMAGE => $this->addImage($plan),
                default => ExecutionResult::fail(sprintf('Unknown intent: %s', $plan->intent), 'UNKNOWN_INTENT'),
            };
        } catch (Throwable $e) {
            $this->aiLogger->error('[PlanExecutor] Execution failed: {message}', [
                'message' => $e->getMessage(),
                'exception' => $e,
            ]);
            return ExecutionResult::fail($e->getMessage(), 'EXECUTION_EXCEPTION');
        }
    }

    /**
     * Adds the image the user attached to the media library, as an "image" content under the folder the plan names.
     * Ibexa keeps its own copy of the file when the content is published: the attachment is left as it is.
     */
    private function addImage(Plan $plan): ExecutionResult
    {
        if ($this->attachments === null || $this->permissions === null || $this->contentFactory === null || $this->repository === null) {
            return ExecutionResult::fail('add_image executor dependencies not configured', 'EXECUTOR_NOT_CONFIGURED');
        }

        $attachment = $this->attachments->find((string) $plan->attachmentId, (int) $this->permissions->getCurrentUserReference()->getUserId());
        $path = $attachment === null ? '' : $this->attachments->path($attachment);
        if ($attachment === null || !is_file($path)) {
            return ExecutionResult::fail('The attached image is no longer available. Attach it again.', 'ATTACHMENT_GONE');
        }

        $title = trim((string) $plan->title) !== '' ? trim((string) $plan->title) : (pathinfo($attachment->name, PATHINFO_FILENAME) ?: 'Image');
        $value = new ImageValue([
            'inputUri' => $path,
            'fileName' => $attachment->name,
            'fileSize' => $attachment->size,
            'width' => $attachment->width,
            'height' => $attachment->height,
            'alternativeText' => trim((string) $plan->description),
        ]);
        $language = $plan->language ?? $this->repository->getContentLanguageService()->getDefaultLanguageCode();

        $result = $this->contentFactory->createAndPublish('image', [(int) $plan->parentLocationId], ['name' => $title, 'image' => $value], $language);
        if (!isset($result['content'], $result['location'])) {
            return ExecutionResult::fail('The image could not be added: the media library did not accept it.', 'ADD_IMAGE_FAILED');
        }

        return ExecutionResult::ok(
            sprintf('Added the image "%s" to the media library (ID: %d)', $title, $result['content']->getId()),
            (int) $result['content']->getId(),
            (int) $result['location']->getId(),
            ['content_id' => $result['content']->getId(), 'location_id' => $result['location']->getId()],
        );
    }

    private function createContent(Plan $plan): ExecutionResult
    {
        $tool = $this->toolRegistry->get(ToolName::CREATE_CONTENT);
        if ($tool === null) {
            return ExecutionResult::fail('create_content tool not available', 'TOOL_UNAVAILABLE');
        }
        $result = $tool->execute(array_filter([
            'content_type' => $plan->contentType,
            'parent_location_id' => $plan->parentLocationId,
            'siteaccess' => $plan->siteaccess,
            'attributes' => $plan->fields,
            'language' => $plan->language,
        ], static fn (mixed $value): bool => $value !== null));

        return $this->toExecutionResult($result, 'content');
    }

    private function toExecutionResult(ToolResult $toolResult, string $entityType): ExecutionResult
    {
        if (!$toolResult->success) {
            return ExecutionResult::fail(
                sprintf('Failed to create %s: %s', $entityType, $toolResult->message),
                'TOOL_FAILED',
            );
        }

        $data = $toolResult->data;
        $contentId = $data['content_id'] ?? $data['page_id'] ?? null;
        $locationId = $data['location_id'] ?? $data['page_location_id'] ?? null;

        return ExecutionResult::ok(
            $toolResult->message !== '' ? $toolResult->message : sprintf('Created %s.', $entityType),
            $contentId !== null ? (int)$contentId : null,
            $locationId !== null ? (int)$locationId : null,
            $data,
        );
    }

    private function updateContent(Plan $plan): ExecutionResult
    {
        $tool = $this->toolRegistry->get(ToolName::UPDATE_CONTENT);
        if ($tool === null) {
            return ExecutionResult::fail('update_content tool not available', 'TOOL_UNAVAILABLE');
        }
        $result = $tool->execute(array_filter([
            'content_id' => $plan->contentId,
            'attributes' => $plan->fields,
            // Absent: the tool keeps the language of the content.
            'language' => $plan->language,
        ], static fn (mixed $value): bool => $value !== null));

        return $this->toExecutionResult($result, 'content');
    }

    private function createFolder(Plan $plan): ExecutionResult
    {
        $tool = $this->toolRegistry->get(ToolName::CREATE_FOLDER);
        if ($tool === null) {
            return ExecutionResult::fail('create_folder tool not available', 'TOOL_UNAVAILABLE');
        }
        $result = $tool->execute(array_filter([
            'name' => $plan->title,
            'parent_location_id' => $plan->parentLocationId,
            'language' => $plan->language,
        ], static fn (mixed $value): bool => $value !== null));

        return $this->toExecutionResult($result, 'folder');
    }
}
