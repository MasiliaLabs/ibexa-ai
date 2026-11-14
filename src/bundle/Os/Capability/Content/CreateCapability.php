<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Os\Capability\Content;

use Masilia\Bundle\IbexaAi\Content\Write\WriteChecks;
use Masilia\Contracts\IbexaAi\Content\ContentWriter;
use Masilia\Contracts\IbexaAi\Content\Languages;
use Masilia\Contracts\IbexaAi\Os\AbstractCapability;
use Masilia\Contracts\IbexaAi\Os\CapabilityResult;
use Masilia\Contracts\IbexaAi\Os\Preview;
use Masilia\Contracts\IbexaAi\Os\Risk;
use Throwable;

final class CreateCapability extends AbstractCapability
{
    public function __construct(
        private readonly WriteChecks $checks,
        private readonly ContentWriter $writer,
        private readonly Languages $languages,
    ) {
    }

    public function name(): string
    {
        return 'content.create';
    }

    public function summary(): string
    {
        return 'Create a content of a given type under a parent, and publish it.';
    }

    public function description(): string
    {
        return 'Creates and publishes one content. The content type must exist and the parent location must exist. Field values follow the formats of the '
            . 'content type. With a siteaccess the content is written in a language of that site (the siteaccess\'s own unless you give another one of the site). '
            . 'The answer has content_id and location_id, which later steps of propose_changes can use.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['content_type', 'parent_location_id', 'fields'],
            'properties' => [
                'content_type' => ['type' => 'string', 'description' => 'Content type identifier, e.g. "article"'],
                'parent_location_id' => ['type' => 'integer', 'minimum' => 1],
                'fields' => ['type' => 'object', 'description' => 'Field identifier => value'],
                'siteaccess' => ['type' => 'string', 'description' => 'The site the content is for: it gives the language'],
                'language' => ['type' => 'string', 'description' => 'Language code or primary code ("fr", "fre-FR"), one of the site\'s'],
            ],
        ];
    }

    public function requires(): array
    {
        return [['content', 'create']];
    }

    public function examples(): array
    {
        return [['description' => 'An article under a folder', 'arguments' => ['content_type' => 'article', 'parent_location_id' => 1018, 'fields' => ['title' => 'Hello'], 'siteaccess' => 'fossil_exit']]];
    }

    public function risk(): Risk
    {
        return Risk::WRITE;
    }

    public function validate(array $arguments): ?string
    {
        $type = (string) ($arguments['content_type'] ?? '');
        $fields = (array) ($arguments['fields'] ?? []);
        if ($type === '') {
            return 'content.create requires content_type.';
        }
        if ($fields === []) {
            return 'content.create requires non-empty fields.';
        }

        return $this->checks->contentType($type, $fields)
            ?? $this->checks->parent((int) $arguments['parent_location_id'], $arguments['siteaccess'] ?? null)
            ?? $this->checks->language($arguments['language'] ?? null, $arguments['siteaccess'] ?? null)[1];
    }

    public function preview(array $arguments): Preview
    {
        $fields = array_keys((array) ($arguments['fields'] ?? []));

        return new Preview('Create a content', array_filter([
            ['Content type', (string) $arguments['content_type']],
            ['Parent location', (string) $arguments['parent_location_id']],
            isset($arguments['siteaccess']) ? ['Site', (string) $arguments['siteaccess']] : null,
            isset($arguments['language']) ? ['Language', (string) $arguments['language']] : null,
            ['Fields', implode(', ', array_slice(array_map('strval', $fields), 0, 14))],
        ]));
    }

    public function execute(array $arguments): CapabilityResult
    {
        [$language] = $this->checks->language($arguments['language'] ?? null, $arguments['siteaccess'] ?? null);
        try {
            $published = $this->writer->create((string) $arguments['content_type'], [(int) $arguments['parent_location_id']], (array) $arguments['fields'], $language ?? $this->languages->default());
        } catch (Throwable $e) {
            return CapabilityResult::error(sprintf('Failed to create content: %s', $e->getMessage()));
        }

        return CapabilityResult::ok(sprintf('Created %s (ID: %d)', $arguments['content_type'], $published->content->getId()), [
            'content_id' => $published->content->getId(),
            'location_id' => $published->location?->getId(),
        ]);
    }
}
