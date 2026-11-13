<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Agent\Worker;

/**
 * Validated plan structure ready for execution.
 */
final readonly class Plan
{
    public const INTENT_CREATE_CONTENT = 'create_content';
    public const INTENT_CREATE_ITEMS = 'create_items';
    public const INTENT_UPDATE_CONTENT = 'update_content';
    public const INTENT_CREATE_FOLDER = 'create_folder';
    public const INTENT_CREATE_SITE_STRUCTURE = 'create_site_structure';
    public const INTENT_TRASH_CONTENT = 'trash_content';
    public const INTENT_RESTORE_CONTENT = 'restore_content';
    public const INTENT_ADD_IMAGE = 'add_image';

    public const INTENTS = [
        self::INTENT_CREATE_CONTENT,
        self::INTENT_CREATE_ITEMS,
        self::INTENT_UPDATE_CONTENT,
        self::INTENT_CREATE_FOLDER,
        self::INTENT_CREATE_SITE_STRUCTURE,
        self::INTENT_TRASH_CONTENT,
        self::INTENT_RESTORE_CONTENT,
        self::INTENT_ADD_IMAGE,
    ];

    /**
     * @param array<int, array<string, mixed>> $blocks Block definitions for content intents
     * @param array<string, mixed>             $fields Field values for content intents
     * @param array<int, array<string, mixed>> $items  Child item definitions for create_items (each: {type, fields})
     */
    public function __construct(
        public string $intent,
        public ?string $title = null,
        public ?string $contentType = null,
        public ?string $siteaccess = null,
        public ?int   $parentLocationId = null,
        public array  $blocks = [],
        public array  $fields = [],
        public ?int   $contentId = null,
        public ?string $description = null,
        public array  $items = [],
        public ?string $linkField = null,
        /** Language code the content is written in; the builder sets it to a language of the site. */
        public ?string $language = null,
        /** The image the user attached to a message, for add_image. */
        public ?string $attachmentId = null,
    ) {
    }

    public function withLanguage(?string $language): self
    {
        return new self(
            intent: $this->intent,
            title: $this->title,
            contentType: $this->contentType,
            siteaccess: $this->siteaccess,
            parentLocationId: $this->parentLocationId,
            blocks: $this->blocks,
            fields: $this->fields,
            contentId: $this->contentId,
            description: $this->description,
            items: $this->items,
            linkField: $this->linkField,
            language: $language,
            attachmentId: $this->attachmentId,
        );
    }

    public function validate(): ?string
    {
        if (!in_array($this->intent, self::INTENTS, true)) {
            return sprintf('Unknown intent: %s', $this->intent);
        }

        return match ($this->intent) {
            self::INTENT_CREATE_CONTENT => $this->validateCreateContent(),
            self::INTENT_CREATE_ITEMS => $this->validateCreateItems(),
            self::INTENT_UPDATE_CONTENT => $this->validateUpdateContent(),
            self::INTENT_CREATE_FOLDER => $this->validateCreateFolder(),
            self::INTENT_CREATE_SITE_STRUCTURE => $this->validateCreateSiteStructure(),
            self::INTENT_TRASH_CONTENT, self::INTENT_RESTORE_CONTENT => $this->validateContentRef(),
            self::INTENT_ADD_IMAGE => $this->validateAddImage(),
        };
    }

    private function validateCreateContent(): ?string
    {
        if ($this->contentType === null || $this->contentType === '') {
            return 'create_content requires contentType';
        }
        if ($this->parentLocationId === null) {
            return 'create_content requires parentLocationId';
        }
        if ($this->fields === []) {
            return 'create_content requires non-empty fields';
        }
        return null;
    }

    private function validateUpdateContent(): ?string
    {
        if ($this->contentId === null) {
            return 'update_content requires contentId';
        }
        if ($this->fields === []) {
            return 'update_content requires non-empty fields';
        }
        return null;
    }

    private function validateCreateItems(): ?string
    {
        if ($this->contentId === null) {
            return 'create_items requires content_id (parent content id)';
        }
        if ($this->items === []) {
            return 'create_items requires non-empty items';
        }
        return null;
    }

    private function validateCreateFolder(): ?string
    {
        if ($this->title === null || $this->title === '') {
            return 'create_folder requires title';
        }
        if ($this->parentLocationId === null) {
            return 'create_folder requires parentLocationId';
        }
        return null;
    }

    private function validateCreateSiteStructure(): ?string
    {
        if ($this->title === null || $this->title === '') {
            return 'create_site_structure requires title (site name)';
        }
        if ($this->blocks === []) {
            return 'create_site_structure requires pages (blocks field)';
        }
        return null;
    }

    private function validateAddImage(): ?string
    {
        if ($this->attachmentId === null || $this->attachmentId === '') {
            return 'add_image requires attachment_id (the id in the image\'s attachment tag)';
        }
        if ($this->parentLocationId === null) {
            return 'add_image requires parent_location_id (an Images folder of the media library)';
        }

        return null;
    }

    private function validateContentRef(): ?string
    {
        if ($this->contentId === null && $this->fields === []) {
            return 'trash/restore requires contentId or content_ids';
        }
        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'intent' => $this->intent,
            'title' => $this->title,
            'content_type' => $this->contentType,
            'siteaccess' => $this->siteaccess,
            'parent_location_id' => $this->parentLocationId,
            'blocks' => $this->blocks,
            'fields' => $this->fields,
            'content_id' => $this->contentId,
            'description' => $this->description,
            'items' => $this->items,
            'link_field' => $this->linkField,
            'language' => $this->language,
            'attachment_id' => $this->attachmentId,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            intent: (string) ($data['intent'] ?? ''),
            title: $data['title'] ?? null,
            contentType: $data['content_type'] ?? null,
            siteaccess: $data['siteaccess'] ?? null,
            parentLocationId: isset($data['parent_location_id']) ? (int) $data['parent_location_id'] : null,
            blocks: $data['blocks'] ?? [],
            fields: $data['fields'] ?? [],
            contentId: isset($data['content_id']) ? (int) $data['content_id'] : null,
            description: $data['description'] ?? null,
            items: $data['items'] ?? [],
            linkField: $data['link_field'] ?? null,
            language: isset($data['language']) && $data['language'] !== '' ? (string) $data['language'] : null,
            attachmentId: isset($data['attachment_id']) && $data['attachment_id'] !== '' ? (string) $data['attachment_id'] : null,
        );
    }
}
