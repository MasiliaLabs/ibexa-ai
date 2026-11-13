<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Os\Capability\Media;

use Masilia\Bundle\IbexaAi\Agent\Worker\Plan;
use Masilia\Bundle\IbexaAi\Os\Capability\PlanCapability;

final class AddImageCapability extends PlanCapability
{
    public function name(): string
    {
        return 'media.add_image';
    }

    public function summary(): string
    {
        return 'Add an image the user attached to a message to the media library.';
    }

    public function description(): string
    {
        return 'Creates an "image" content in a media folder from an image the user attached (its id is in the <attachment-image> tag of the message). '
            . 'The parent must be an Images folder of the media library: find it with tree.browse under the Media location. '
            . 'The title is the name of the content (by default the file name without its extension). The answer has content_id and location_id.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['attachment_id', 'parent_location_id'],
            'properties' => [
                'attachment_id' => ['type' => 'string', 'description' => 'The id in the image\'s <attachment-image> tag'],
                'parent_location_id' => ['type' => 'integer', 'minimum' => 1, 'description' => 'An Images folder of the media library'],
                'title' => ['type' => 'string', 'maxLength' => 190],
                'alternative_text' => ['type' => 'string', 'maxLength' => 300, 'description' => 'In the language of the site; leave it out rather than invent it'],
                'language' => ['type' => 'string'],
            ],
        ];
    }

    public function requires(): array
    {
        return [['content', 'create']];
    }

    public function examples(): array
    {
        return [['description' => 'Put an attached logo in a site\'s images', 'arguments' => ['attachment_id' => '3f2b8c1e-5d6a-4e7f-9a10-2b3c4d5e6f70', 'parent_location_id' => 1020, 'title' => 'Logo', 'alternative_text' => 'The campaign logo']]];
    }

    protected function plan(array $arguments): array
    {
        return ['intent' => Plan::INTENT_ADD_IMAGE] + array_filter([
            'attachment_id' => $arguments['attachment_id'] ?? null,
            'parent_location_id' => $arguments['parent_location_id'] ?? null,
            'title' => $arguments['title'] ?? null,
            'description' => $arguments['alternative_text'] ?? null,
            'language' => $arguments['language'] ?? null,
        ], static fn (mixed $value): bool => $value !== null);
    }
}
