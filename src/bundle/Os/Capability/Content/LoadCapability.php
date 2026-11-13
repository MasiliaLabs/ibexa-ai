<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Os\Capability\Content;

use Masilia\Bundle\IbexaAi\Agent\Tool\ToolInterface;
use Masilia\Bundle\IbexaAi\Os\Capability\BaseCapability;
use Masilia\IbexaAi\Os\CapabilityResult;
use Masilia\IbexaAi\Os\Risk;

final class LoadCapability extends BaseCapability
{
    public function __construct(private readonly ToolInterface $loadTool)
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
        return self::fromTool($this->loadTool->execute($arguments));
    }
}
