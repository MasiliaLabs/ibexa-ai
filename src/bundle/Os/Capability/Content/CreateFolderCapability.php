<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Os\Capability\Content;

use Masilia\Bundle\IbexaAi\Ibexa\Content\Write\WriteChecks;
use Masilia\Contracts\IbexaAi\Content\ContentWriter;
use Masilia\Contracts\IbexaAi\Content\Languages;
use Masilia\Contracts\IbexaAi\Os\AbstractCapability;
use Masilia\Contracts\IbexaAi\Os\CapabilityResult;
use Masilia\Contracts\IbexaAi\Os\Preview;
use Masilia\Contracts\IbexaAi\Os\Risk;
use Throwable;

final class CreateFolderCapability extends AbstractCapability
{
    /** The folder content type of a stock Ibexa repository: it holds a name. */
    private const FOLDER_TYPE = 'folder';

    public function __construct(
        private readonly WriteChecks $checks,
        private readonly ContentWriter $writer,
        private readonly Languages $languages,
    ) {
    }

    public function name(): string
    {
        return 'content.create_folder';
    }

    public function summary(): string
    {
        return 'Create a folder under a location.';
    }

    public function description(): string
    {
        return 'Creates and publishes a folder with the given name under a parent location. The answer has content_id and location_id, '
            . 'which later steps of propose_changes can use as the parent of what goes in it.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['name', 'parent_location_id'],
            'properties' => [
                'name' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 190],
                'parent_location_id' => ['type' => 'integer', 'minimum' => 1],
                'siteaccess' => ['type' => 'string', 'description' => 'The site the folder is for: it gives the language'],
                'language' => ['type' => 'string', 'description' => 'Language code or primary code, one of the site\'s'],
            ],
        ];
    }

    public function requires(): array
    {
        return [['content', 'create']];
    }

    public function examples(): array
    {
        return [['description' => 'A folder at the root of a site', 'arguments' => ['name' => 'Press kit', 'parent_location_id' => 1018, 'siteaccess' => 'fossil_exit']]];
    }

    public function risk(): Risk
    {
        return Risk::WRITE;
    }

    public function validate(array $arguments): ?string
    {
        return $this->checks->parent((int) $arguments['parent_location_id'], $arguments['siteaccess'] ?? null)
            ?? $this->checks->language($arguments['language'] ?? null, $arguments['siteaccess'] ?? null)[1];
    }

    public function preview(array $arguments): Preview
    {
        return new Preview('Create a folder', array_filter([
            ['Title', (string) $arguments['name']],
            ['Parent location', (string) $arguments['parent_location_id']],
            isset($arguments['siteaccess']) ? ['Site', (string) $arguments['siteaccess']] : null,
            isset($arguments['language']) ? ['Language', (string) $arguments['language']] : null,
        ]));
    }

    public function execute(array $arguments): CapabilityResult
    {
        [$language] = $this->checks->language($arguments['language'] ?? null, $arguments['siteaccess'] ?? null);
        try {
            $published = $this->writer->create(self::FOLDER_TYPE, [(int) $arguments['parent_location_id']], ['name' => (string) $arguments['name']], $language ?? $this->languages->default());
        } catch (Throwable $e) {
            return CapabilityResult::error(sprintf('Failed to create folder: %s', $e->getMessage()));
        }

        return CapabilityResult::ok(sprintf('Created folder "%s" (ID: %d)', $arguments['name'], $published->content->getId()), [
            'content_id' => $published->content->getId(),
            'location_id' => $published->location?->getId(),
        ]);
    }
}
