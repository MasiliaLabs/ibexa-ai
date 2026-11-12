<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Agent\Tool\Content;

use Ibexa\Contracts\Core\Repository\Repository;
use Masilia\Bundle\IbexaAi\Agent\Tool\AgentErrorHelper;
use Masilia\Bundle\IbexaAi\Agent\Tool\ContentUpdater;
use Masilia\Bundle\IbexaAi\Agent\Tool\ToolInterface;
use Masilia\Bundle\IbexaAi\Agent\Tool\ToolName;
use Masilia\Bundle\IbexaAi\Agent\Tool\ToolResult;
use Masilia\Bundle\IbexaAi\Support\AiConstants;
use Psr\Log\LoggerInterface;

readonly class UpdateContentTool implements ToolInterface
{
    public function __construct(
        private Repository $repository,
        private ContentUpdater $contentUpdater,
        private LoggerInterface $aiLogger,
    ) {
    }

    public function getName(): string
    {
        return ToolName::UPDATE_CONTENT;
    }

    public function getDescription(): string
    {
        return 'Update fields on an existing content item.';
    }

    public function getParameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'content_id' => [
                    'type' => 'integer',
                    'description' => 'Content ID to update',
                ],
                'attributes' => [
                    'type' => 'object',
                    'description' => 'Field values to update as key-value pairs',
                ],
                'language' => [
                    'type' => 'string',
                    'description' => 'Language code the content is written in, e.g. "fre-FR" (default: the repository default language)',
                    'default' => AiConstants::DEFAULT_LANGUAGE_CODE,
                ],
            ],
            'required' => ['content_id', 'attributes'],
        ];
    }

    public function execute(array $params): ToolResult
    {
        try {
            $contentId = (int) $params['content_id'];
            $attributes = $params['attributes'] ?? [];
            // Without a language the update stays in the language of the content, not in the repository default.
            $languageCode = $params['language']
                ?? $this->repository->getContentService()->loadContentInfo($contentId)->getMainLanguageCode();

            $published = $this->contentUpdater->update($contentId, $attributes, $languageCode);

            return ToolResult::ok(
                sprintf('Updated content %d', $contentId),
                [
                    'content_id' => $published->getId(),
                    'version_no' => $published->getVersionInfo()->getVersionNo(),
                ],
            );
        } catch (\Throwable $e) {
            return AgentErrorHelper::handle($this->aiLogger, $e, 'update content');
        }
    }
}
