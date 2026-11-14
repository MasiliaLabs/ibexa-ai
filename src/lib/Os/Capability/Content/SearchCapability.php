<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Os\Capability\Content;

use Masilia\IbexaAi\Ibexa\Content\Read\ContentSearch;
use Masilia\Contracts\IbexaAi\Os\AbstractCapability;
use Masilia\Contracts\IbexaAi\Os\CapabilityResult;
use Masilia\Contracts\IbexaAi\Os\Risk;

final class SearchCapability extends AbstractCapability
{
    public function __construct(private readonly ContentSearch $search)
    {
    }

    public function name(): string
    {
        return 'content.search';
    }

    public function summary(): string
    {
        return 'Find contents by name, text, content type or place in the tree.';
    }

    public function description(): string
    {
        return 'Searches the contents the current user can read. Filters combine: a content type, a name (with * as a wildcard), '
            . 'a full-text query, and a subtree (a location and everything under it). Each result has content_id, content_type, name, '
            . 'languages and whether it is visible to visitors. Page through long results with offset: total is the exact count.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'content_type' => ['type' => 'string', 'description' => 'Content type identifier, e.g. "page"'],
                'name' => ['type' => 'string', 'description' => 'The name, with * as a wildcard, e.g. "about*"'],
                'query' => ['type' => 'string', 'description' => 'Full-text query'],
                'subtree_location_id' => ['type' => 'integer', 'minimum' => 1, 'description' => 'A location: search under it'],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'description' => 'Most results (default 10)'],
                'offset' => ['type' => 'integer', 'minimum' => 0, 'description' => 'Results to skip, to page'],
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
            ['description' => 'Pages whose name starts with "About"', 'arguments' => ['content_type' => 'page', 'name' => 'About*']],
            ['description' => 'Everything under a location that mentions "energy"', 'arguments' => ['subtree_location_id' => 2, 'query' => 'energy', 'limit' => 20]],
        ];
    }

    public function execute(array $arguments): CapabilityResult
    {
        $found = $this->search->search($arguments);

        return CapabilityResult::ok(sprintf('Found %d results', $found['count']), $found);
    }
}
