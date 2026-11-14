<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Os\Capability\Media;

use Ibexa\Core\FieldType\Image\Value as ImageValue;
use Ibexa\Core\FieldType\ImageAsset\AssetMapper;
use Ibexa\Contracts\Core\Repository\PermissionResolver;
use Masilia\IbexaAi\Chat\Attachment\AttachmentStore;
use Masilia\IbexaAi\Ibexa\Content\Write\WriteChecks;
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
        private readonly Languages $languages,
        private readonly AttachmentStore $attachments,
        private readonly PermissionResolver $permissions,
        private readonly AssetMapper $assets,
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
        return 'Adds an image the user attached (its id is in the <attachment-image> tag of the message) to the media library, where the admin puts the images it uploads: '
            . 'the folder and the content type are the ones the project configured for image assets (fieldtypes.ibexa_image_asset.mappings). '
            . 'The title is the name of the content (by default the file name without its extension). The answer has content_id and location_id.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['attachment_id'],
            'properties' => [
                'attachment_id' => ['type' => 'string', 'description' => 'The id in the image\'s <attachment-image> tag'],
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
        return [['description' => 'Put an attached logo in the media library', 'arguments' => ['attachment_id' => '3f2b8c1e-5d6a-4e7f-9a10-2b3c4d5e6f70', 'title' => 'Logo', 'alternative_text' => 'The campaign logo']]];
    }

    public function risk(): Risk
    {
        return Risk::WRITE;
    }

    public function validate(array $arguments): ?string
    {
        return $this->checks->attachment((string) $arguments['attachment_id'])
            ?? $this->checks->language($arguments['language'] ?? null)[1];
    }

    public function preview(array $arguments): Preview
    {
        $rows = [
            ['Image', 'attached by you'],
            ['Where', sprintf('the media library, location %d (the folder of the image assets)', $this->assets->getParentLocationId())],
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
            $content = $this->assets->createAsset($title, $value, $language ?? $this->languages->default());
        } catch (Throwable $e) {
            return CapabilityResult::error(sprintf('The image could not be added: %s', $e->getMessage()));
        }

        return CapabilityResult::ok(sprintf('Added the image "%s" to the media library (ID: %d)', $title, $content->getId()), [
            'content_id' => $content->getId(),
            'location_id' => $content->getContentInfo()->getMainLocationId(),
        ]);
    }
}
