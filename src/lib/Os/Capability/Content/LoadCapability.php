<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Os\Capability\Content;

use Masilia\IbexaAi\Ibexa\Content\Read\ContentLoader;
use Masilia\Contracts\IbexaAi\Os\AbstractCapability;
use Masilia\Contracts\IbexaAi\Os\CapabilityResult;
use Masilia\Contracts\IbexaAi\Os\Risk;

final class LoadCapability extends AbstractCapability
{
    public function __construct(private readonly ContentLoader $loader)
    {
    }

    public function name(): string
    {
        return 'content.load';
    }

    public function summary(): string
    {
        return 'Read a content: its metadata and the text of its fields.';
    }

    public function description(): string
    {
        return 'Loads one content by content_id, remote_id or location_id and returns its name, content type, main location, '
            . 'the languages it exists in, and the value of each field as readable text, in the language asked (by default its main language).';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'content_id' => ['type' => 'integer', 'minimum' => 1],
                'remote_id' => ['type' => 'string'],
                'location_id' => ['type' => 'integer', 'minimum' => 1],
                'language' => ['type' => 'string', 'description' => 'Language code, e.g. "fre-FR"'],
            ],
        ];
    }

    public function risk(): Risk
    {
        return Risk::READ;
    }

    public function requires(): array
    {
        return [['content', 'read']];
    }

    public function examples(): array
    {
        return [['description' => 'The French version of a content', 'arguments' => ['content_id' => 1353, 'language' => 'fre-FR']]];
    }

    public function execute(array $arguments): CapabilityResult
    {
        $content = $this->loader->load($arguments);

        return CapabilityResult::ok(sprintf('Loaded content: %s (ID: %d)', $content['name'] ?? 'unnamed', $content['content_id']), $content);
    }
}
