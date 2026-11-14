<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Agent\Tool\Structural;

use Ibexa\Contracts\Core\Repository\Repository;
use Masilia\Bundle\IbexaAi\Agent\Tool\AgentErrorHelper;
use Masilia\Bundle\IbexaAi\Agent\Tool\ContentCreator;
use Masilia\Bundle\IbexaAi\Agent\Tool\ToolInterface;
use Masilia\Bundle\IbexaAi\Agent\Tool\ToolName;
use Masilia\Bundle\IbexaAi\Agent\Tool\ToolResult;
use Masilia\Bundle\IbexaAi\Support\AiConstants;
use Psr\Log\LoggerInterface;

readonly class CreateFolderTool implements ToolInterface
{
    /** The folder of a stock Ibexa repository: its content type and the field that holds its name. */
    private const FOLDER_TYPE = 'folder';
    private const NAME_FIELD = 'name';

    public function __construct(
        private Repository $repository,
        private ContentCreator $contentCreator,
        private LoggerInterface $aiLogger,
    ) {
    }

    public function getName(): string
    {
        return ToolName::CREATE_FOLDER;
    }

    public function getDescription(): string
    {
        return 'Create a folder content type in the content tree.';
    }

    public function getParameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'name' => [
                    'type' => 'string',
                    'description' => 'Folder name',
                ],
                'parent_location_id' => [
                    'type' => 'integer',
                    'description' => 'Parent location ID',
                ],
                'language' => [
                    'type' => 'string',
                    'description' => 'Language code the content is written in, e.g. "fre-FR" (default: the repository default language)',
                    'default' => AiConstants::DEFAULT_LANGUAGE_CODE,
                ],
            ],
            'required' => ['name', 'parent_location_id'],
        ];
    }

    public function execute(array $params): ToolResult
    {
        try {
            $languageCode = $params['language']
                ?? $this->repository->getContentLanguageService()->getDefaultLanguageCode();

            $result = $this->contentCreator->createAndPublish(
                self::FOLDER_TYPE,
                [(int) $params['parent_location_id']],
                [self::NAME_FIELD => $params['name']],
                $languageCode,
            );

            return ToolResult::ok(
                sprintf('Created folder "%s" (ID: %d)', $params['name'], $result['content']->getId()),
                [
                    'content_id' => $result['content']->getId(),
                    'location_id' => $result['location']->getId(),
                ],
            );
        } catch (\Throwable $e) {
            return AgentErrorHelper::handle($this->aiLogger, $e, 'create folder');
        }
    }
}
