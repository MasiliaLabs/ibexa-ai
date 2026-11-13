<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Os\Capability\Content;

use Ibexa\Contracts\Core\Repository\Repository;
use Masilia\Bundle\IbexaAi\Agent\Tool\ToolInterface;
use Masilia\Bundle\IbexaAi\Os\Capability\BaseCapability;
use Masilia\IbexaAi\Os\CapabilityResult;
use Masilia\IbexaAi\Os\Preview;
use Masilia\IbexaAi\Os\Risk;
use Throwable;

final class UpdateCapability extends BaseCapability
{
    public function __construct(
        private readonly ToolInterface $updateTool,
        private readonly Repository $repository,
    ) {
    }

    public function name(): string
    {
        return 'content.update';
    }

    public function summary(): string
    {
        return 'Change the fields of a content, in one language, and publish it.';
    }

    public function description(): string
    {
        return 'Sets the given fields of an existing content in one language and publishes the new version. Only the fields given change. '
            . 'Without a language, the content is changed in its own main language. To write a translation that does not exist yet, '
            . 'give the language: it is created, and the other languages are left as they are. Field values follow the formats of the '
            . 'content type (read the content first to see them).';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['content_id', 'fields'],
            'properties' => [
                'content_id' => ['type' => 'integer', 'minimum' => 1],
                'fields' => ['type' => 'object', 'description' => 'Field identifier => new value'],
                'language' => ['type' => 'string', 'description' => 'Language code, e.g. "fre-FR"'],
            ],
        ];
    }

    public function risk(): Risk
    {
        return Risk::WRITE;
    }

    public function requires(): array
    {
        return [['content', 'edit'], ['content', 'publish']];
    }

    public function examples(): array
    {
        return [['description' => 'Retitle a page', 'arguments' => ['content_id' => 1353, 'fields' => ['title' => 'New title']]]];
    }

    public function preview(array $arguments): Preview
    {
        $id = (int) $arguments['content_id'];
        try {
            $name = (string) $this->repository->getContentService()->loadContentInfo($id)->getName();
        } catch (Throwable) {
            $name = '';
        }

        return new Preview('Update content', [
            ['Content', $name !== '' ? sprintf('%s (%d)', $name, $id) : (string) $id],
            ['Language', isset($arguments['language']) ? (string) $arguments['language'] : 'its own language'],
            ['Fields', implode(', ', array_map('strval', array_keys((array) $arguments['fields'])))],
        ], ['A new version is published.']);
    }

    public function execute(array $arguments): CapabilityResult
    {
        return self::fromTool($this->updateTool->execute(array_filter([
            'content_id' => $arguments['content_id'],
            'attributes' => $arguments['fields'],
            'language' => $arguments['language'] ?? null,
        ], static fn (mixed $value): bool => $value !== null)));
    }
}
