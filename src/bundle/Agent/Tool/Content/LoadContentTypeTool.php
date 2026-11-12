<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Agent\Tool\Content;

use Ibexa\Contracts\Core\Repository\Repository;
use Masilia\Bundle\IbexaAi\Agent\Tool\AgentErrorHelper;
use Masilia\Bundle\IbexaAi\Agent\Tool\ToolInterface;
use Masilia\Bundle\IbexaAi\Agent\Tool\ToolName;
use Masilia\Bundle\IbexaAi\Agent\Tool\ToolResult;
use Psr\Log\LoggerInterface;

readonly class LoadContentTypeTool implements ToolInterface
{
    public function __construct(
        private Repository $repository,
        private LoggerInterface $aiLogger,
    ) {
    }

    public function getName(): string
    {
        return ToolName::LOAD_CONTENT_TYPE;
    }

    public function getDescription(): string
    {
        return 'Load content type definition including all field definitions.';
    }

    public function getParameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'identifier' => [
                    'type' => 'string',
                    'description' => 'Content type identifier (e.g., "article", "page")',
                ],
            ],
            'required' => ['identifier'],
        ];
    }

    public function execute(array $params): ToolResult
    {
        try {
            $contentType = $this->repository->getContentTypeService()
                ->loadContentTypeByIdentifier($params['identifier']);

            $fields = [];
            foreach ($contentType->getFieldDefinitions() as $fieldDef) {
                $fields[$fieldDef->getIdentifier()] = [
                    'type' => $fieldDef->getFieldTypeIdentifier(),
                    'name' => $fieldDef->getName(),
                    'required' => $fieldDef->isRequired(),
                    'identifier' => $fieldDef->getIdentifier(),
                ];
            }

            return ToolResult::ok(
                sprintf('Loaded content type: %s', $contentType->getIdentifier()),
                [
                    'identifier' => $contentType->getIdentifier(),
                    'name' => $contentType->getName(),
                    'group' => $contentType->getContentTypeGroups()[0]->identifier ?? 'unknown',
                    'is_container' => $contentType->isContainer(),
                    'fields' => $fields,
                ],
            );
        } catch (\Throwable $e) {
            return AgentErrorHelper::handle($this->aiLogger, $e, 'load content type');
        }
    }
}
