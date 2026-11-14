<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Os\Capability\Tree;

use Masilia\IbexaAi\Ibexa\Content\Read\LocationBrowser;
use Masilia\Contracts\IbexaAi\Os\AbstractCapability;
use Masilia\Contracts\IbexaAi\Os\CapabilityResult;
use Masilia\Contracts\IbexaAi\Os\Risk;

final class BrowseCapability extends AbstractCapability
{
    public function __construct(private readonly LocationBrowser $browser)
    {
    }

    public function name(): string
    {
        return 'tree.browse';
    }

    public function summary(): string
    {
        return 'List the contents under a location (its whole subtree): names, types, languages, visibility, depth.';
    }

    public function description(): string
    {
        return 'Lists the contents under a location, the whole subtree: for example the blocks inside a page, or the folders of the media library. '
            . 'Filter by name (with * as a wildcard), full text, or content type. Each item has content_id, content_type, name, languages and visible '
            . '(false when visitors cannot see it). The answer says how many levels the subtree goes below the location (max_depth_below), '
            . 'how many contents match (total) and how many are returned: page with offset when returned is lower than total. '
            . 'Counting is done for you: set missing_language to a language code to list the contents with no translation in it, only_hidden=true '
            . 'to list the ones visitors cannot see, include_fields=true (with a small limit) to read the text of each item.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['location_id'],
            'properties' => [
                'location_id' => ['type' => 'integer', 'minimum' => 1, 'description' => 'The location to list under'],
                'query' => ['type' => 'string', 'description' => 'Full-text query'],
                'name' => ['type' => 'string', 'description' => 'The name, with * as a wildcard'],
                'content_type' => ['type' => 'string', 'description' => 'Content type identifier'],
                'missing_language' => ['type' => 'string', 'description' => 'Language code: keep only contents with NO translation in it'],
                'only_hidden' => ['type' => 'boolean', 'description' => 'Keep only what visitors cannot see'],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 200, 'description' => 'Most results (default 50)'],
                'offset' => ['type' => 'integer', 'minimum' => 0, 'description' => 'Results to skip, to page'],
                'include_fields' => ['type' => 'boolean', 'description' => 'Also return the text of each item\'s fields'],
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
        return [
            ['description' => 'What is under the media library', 'arguments' => ['location_id' => 43, 'content_type' => 'folder']],
            ['description' => 'Contents with no French translation', 'arguments' => ['location_id' => 1018, 'missing_language' => 'fre-FR']],
        ];
    }

    public function execute(array $arguments): CapabilityResult
    {
        $browsed = $this->browser->browse($arguments);

        return CapabilityResult::ok($browsed['message'], $browsed['data']);
    }
}
