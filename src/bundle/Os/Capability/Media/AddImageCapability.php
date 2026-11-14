<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Os\Capability\Media;

use Ibexa\Core\FieldType\Image\Value as ImageValue;
use Ibexa\Contracts\Core\Repository\PermissionResolver;
use Masilia\Bundle\IbexaAi\Agent\Attachment\AttachmentStore;
use Masilia\Bundle\IbexaAi\Content\Write\WriteChecks;
use Masilia\Contracts\IbexaAi\Content\ContentWriter;
use Masilia\Contracts\IbexaAi\Content\Languages;
use Masilia\Contracts\IbexaAi\Os\AbstractCapability;
use Masilia\Contracts\IbexaAi\Os\CapabilityResult;
use Masilia\Contracts\IbexaAi\Os\Preview;
use Masilia\Contracts\IbexaAi\Os\Risk;
use Throwable;

final class AddImageCapability extends AbstractCapability
{
    public function __construct(
        private readonly WriteChecks $checks,
        private readonly ContentWriter $writer,
        private readonly Languages $languages,
        private readonly AttachmentStore $attachments,
        private readonly PermissionResolver $permissions,
    ) {
    }

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

    public function risk(): Risk
    {
        return Risk::WRITE;
    }

    public function validate(array $arguments): ?string
    {
        return $this->checks->attachment((string) $arguments['attachment_id'])
            ?? $this->checks->parent((int) $arguments['parent_location_id'])
            ?? $this->checks->language($arguments['language'] ?? null)[1];
    }

    public function preview(array $arguments): Preview
    {
        $rows = [
            ['Image', 'attached by you'],
            ['Parent location', (string) $arguments['parent_location_id']],
        ];
        if (isset($arguments['title'])) {
            array_unshift($rows, ['Title', (string) $arguments['title']]);
        }
        if (isset($arguments['language'])) {
            $rows[] = ['Language', (string) $arguments['language']];
        }
        $notes = isset($arguments['alternative_text']) ? [sprintf('Alternative text: %s', $arguments['alternative_text'])] : [];

        return new Preview('Add an image to the media library', $rows, $notes);
    }

    public function execute(array $arguments): CapabilityResult
    {
        $userId = (int) $this->permissions->getCurrentUserReference()->getUserId();
        $attachment = $this->attachments->find((string) $arguments['attachment_id'], $userId);
        $path = $attachment === null ? '' : $this->attachments->path($attachment);
        if ($attachment === null || !is_file($path)) {
            return CapabilityResult::error('The attached image is no longer available. Attach it again.');
        }

        $title = trim((string) ($arguments['title'] ?? '')) !== '' ? trim((string) $arguments['title']) : (pathinfo($attachment->name, PATHINFO_FILENAME) ?: 'Image');
        $value = new ImageValue([
            'inputUri' => $path,
            'fileName' => $attachment->name,
            'fileSize' => $attachment->size,
            'width' => $attachment->width,
            'height' => $attachment->height,
            'alternativeText' => trim((string) ($arguments['alternative_text'] ?? '')),
        ]);
        [$language] = $this->checks->language($arguments['language'] ?? null);

        try {
            $published = $this->writer->create('image', [(int) $arguments['parent_location_id']], ['name' => $title, 'image' => $value], $language ?? $this->languages->default());
        } catch (Throwable $e) {
            return CapabilityResult::error(sprintf('The image could not be added: %s', $e->getMessage()));
        }

        return CapabilityResult::ok(sprintf('Added the image "%s" to the media library (ID: %d)', $title, $published->content->getId()), [
            'content_id' => $published->content->getId(),
            'location_id' => $published->location?->getId(),
        ]);
    }
}
