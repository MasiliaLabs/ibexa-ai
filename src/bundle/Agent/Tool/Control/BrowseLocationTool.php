<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Agent\Tool\Control;

use Ibexa\Contracts\Core\Repository\Repository;
use Ibexa\Contracts\Core\Repository\Values\Content\LocationQuery;
use Ibexa\Contracts\Core\Repository\Values\Content\Query;
use Ibexa\Contracts\Core\Repository\Values\Content\Query\Criterion;
use Ibexa\Contracts\Core\Repository\Values\Content\Query\SortClause;
use Masilia\Bundle\IbexaAi\Agent\Tool\Content\LoadContentTool;
use Masilia\Bundle\IbexaAi\Agent\Tool\Content\SearchContentTool;
use Masilia\Bundle\IbexaAi\Agent\Tool\ToolInterface;
use Masilia\Bundle\IbexaAi\Agent\Tool\ToolResult;

/**
 * Lists the content under a location (deeper than explore_site).
 *
 * explore_site returns the top of a site tree: pages and folders, but not the blocks inside each
 * page. This tool searches a subtree by name, full text or content type, and returns the content
 * ids needed to update or trash individual items.
 *
 * The result always says how many contents match in total, so a truncated list is never mistaken
 * for a complete one. Questions about translations are answered exactly with missing_language,
 * which filters on the server instead of leaving the model to count rows.
 */
final readonly class BrowseLocationTool implements ToolInterface
{
    private const DEFAULT_LIMIT = 50;
    private const MAX_LIMIT = 200;
    /** Page size and ceiling of the server-side scan used by missing_language. */
    private const SCAN_PAGE = 200;
    private const SCAN_MAX = 5000;

    public function __construct(
        // Concrete tools, not the registry: the registry contains this tool, so depending on it would be circular.
        private SearchContentTool $searchTool,
        private LoadContentTool $loadTool,
        private Repository $repository,
    ) {
    }

    public function getName(): string
    {
        return 'browse_location';
    }

    public function getDescription(): string
    {
        return 'List the content under a location (its whole subtree), e.g. the blocks inside a page. ' .
            'Use it after explore_site to find the content IDs of individual blocks or items. ' .
            'Supports name search, full-text query and content type filter. ' .
            'Each result has content_id, content_type, name and languages (the translations that exist, ' .
            'e.g. ["eng-GB","fre-FR"]) and visible (false when visitors cannot see it: hidden, or under a hidden location). ' .
            'To list what is not visible on the site, set only_hidden=true. The result also gives max_depth_below (how many levels the subtree ' .
            'goes below the location: use it for depth questions instead of guessing), total (how many contents match) and ' .
            'returned: when returned is lower than total, page with offset instead of repeating the call per type. ' .
            'To answer "which contents have no translation in language X", set missing_language to X: ' .
            'the filtering is done for you and total is the exact count. ' .
            'Set include_fields=true to also get the field values of each item ' .
            '(to read the text of a page, or before updating a matrix or other fields).';
    }

    public function getParameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'location_id' => [
                    'type' => 'integer',
                    'description' => 'Location ID to search under (subtree)',
                ],
                'query' => [
                    'type' => 'string',
                    'description' => 'Full-text search query (e.g. "cookie policy", "what are cookies")',
                ],
                'name' => [
                    'type' => 'string',
                    'description' => 'Search by content name (supports * wildcards, e.g. "paragraph*" or "cookie*")',
                ],
                'content_type' => [
                    'type' => 'string',
                    'description' => 'Filter by content type identifier (e.g. "hero_banner", "page")',
                ],
                'missing_language' => [
                    'type' => 'string',
                    'description' => 'Language code (e.g. "eng-GB"): keep only the contents that have NO translation in it',
                ],
                'only_hidden' => [
                    'type' => 'boolean',
                    'description' => 'When true, keep only the contents visitors cannot see (hidden, or under a hidden location)',
                    'default' => false,
                ],
                'limit' => [
                    'type' => 'integer',
                    'description' => sprintf('Max results per call (default: %d, max: %d)', self::DEFAULT_LIMIT, self::MAX_LIMIT),
                    'default' => self::DEFAULT_LIMIT,
                ],
                'offset' => [
                    'type' => 'integer',
                    'description' => 'Number of results to skip, to page through a long list (default: 0)',
                    'default' => 0,
                ],
                'include_fields' => [
                    'type' => 'boolean',
                    'description' => 'When true, each result includes its current field values (default: false). Use a small limit with it.',
                    'default' => false,
                ],
            ],
            'required' => ['location_id'],
        ];
    }

    public function execute(array $arguments): ToolResult
    {
        $locationId = $arguments['location_id'] ?? null;
        if (!is_int($locationId) || $locationId <= 0) {
            return ToolResult::error('browse_location requires a valid location_id');
        }

        $limit = max(1, min(self::MAX_LIMIT, (int) ($arguments['limit'] ?? self::DEFAULT_LIMIT)));
        $offset = max(0, (int) ($arguments['offset'] ?? 0));
        $missingLanguage = is_string($arguments['missing_language'] ?? null) ? trim($arguments['missing_language']) : '';

        $filters = ['subtree_location_id' => $locationId];
        foreach (['query', 'name', 'content_type'] as $key) {
            if (is_string($arguments[$key] ?? null) && $arguments[$key] !== '') {
                $filters[$key] = $arguments[$key];
            }
        }

        $onlyHidden = ($arguments['only_hidden'] ?? false) === true;

        // Server-side filters: the count is exact and does not depend on the model reading every row.
        $keep = null;
        if ($missingLanguage !== '' || $onlyHidden) {
            $keep = static fn (array $item): bool => ($missingLanguage === '' || !in_array($missingLanguage, $item['languages'] ?? [], true))
                && (!$onlyHidden || ($item['visible'] ?? null) === false);
        }

        $page = $keep !== null
            ? $this->scan($keep, $filters, $limit, $offset)
            : $this->search($filters, $limit, $offset);

        if ($page instanceof ToolResult) {
            return $page;
        }

        $includeFields = ($arguments['include_fields'] ?? false) === true;
        $items = [];
        foreach ($page['results'] as $item) {
            $entry = [
                'content_id' => $item['content_id'] ?? null,
                'content_type' => $item['content_type'] ?? null,
                'name' => $item['name'] ?? null,
                'remote_id' => $item['remote_id'] ?? null,
                'languages' => $item['languages'] ?? [],
                'visible' => $item['visible'] ?? null,
            ];

            if ($includeFields && ($item['content_id'] ?? null) !== null) {
                $entry['fields'] = $this->loadContentFields((int) $item['content_id']);
            }

            $items[] = $entry;
        }

        $total = $page['total'];
        $data = [
            'location_id' => $locationId,
            'total' => $total,
            // How many levels the subtree goes below this location (1 = children only).
            'max_depth_below' => $this->maxDepthBelow($locationId),
            'returned' => count($items),
            'offset' => $offset,
            'items' => $items,
        ];
        $conditions = [];
        if ($missingLanguage !== '') {
            $data['missing_language'] = $missingLanguage;
            $conditions[] = sprintf('have no %s translation', $missingLanguage);
        }
        if ($onlyHidden) {
            $data['only_hidden'] = true;
            $conditions[] = 'are not visible on the site';
        }
        if ($page['incomplete'] ?? false) {
            $data['incomplete'] = sprintf('Only the first %d contents of the subtree were checked.', self::SCAN_MAX);
        }

        $message = $conditions !== []
            ? sprintf('%d contents under location %d %s (%d returned)', $total, $locationId, implode(' and ', $conditions), count($items))
            : sprintf('Found %d items under location %d (%d returned from offset %d)', $total, $locationId, count($items), $offset);

        return ToolResult::ok($message, $data);
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{results: list<array<string, mixed>>, total: int}|ToolResult
     */
    private function search(array $filters, int $limit, int $offset): array|ToolResult
    {
        $result = $this->searchTool->execute($filters + ['limit' => $limit, 'offset' => $offset]);
        if (!$result->success) {
            return ToolResult::error($result->message);
        }

        return [
            'results' => array_values($result->data['results'] ?? []),
            'total' => (int) ($result->data['count'] ?? 0),
        ];
    }

    /**
     * Scans the whole subtree and keeps the contents accepted by $keep.
     * The count is exact (up to SCAN_MAX contents); only the requested page of matches is returned.
     *
     * @param callable(array<string, mixed>): bool $keep
     * @param array<string, mixed> $filters
     * @return array{results: list<array<string, mixed>>, total: int, incomplete: bool}|ToolResult
     */
    private function scan(callable $keep, array $filters, int $limit, int $offset): array|ToolResult
    {
        $matches = [];
        $scanned = 0;
        $total = null;

        do {
            $page = $this->search($filters, self::SCAN_PAGE, $scanned);
            if ($page instanceof ToolResult) {
                return $page;
            }
            $total ??= $page['total'];

            foreach ($page['results'] as $item) {
                if ($keep($item)) {
                    $matches[] = $item;
                }
            }
            $scanned += count($page['results']);
        } while ($page['results'] !== [] && $scanned < $total && $scanned < self::SCAN_MAX);

        return [
            'results' => array_slice($matches, $offset, $limit),
            'total' => count($matches),
            'incomplete' => $scanned < $total,
        ];
    }

    /**
     * Number of levels between this location and its deepest descendant, or null when it cannot be read.
     */
    private function maxDepthBelow(int $locationId): ?int
    {
        try {
            $location = $this->repository->getLocationService()->loadLocation($locationId);
            $deepest = $this->repository->getSearchService()->findLocations(new LocationQuery([
                'filter' => new Criterion\Subtree($location->getPathString()),
                'sortClauses' => [new SortClause\Location\Depth(Query::SORT_DESC)],
                'limit' => 1,
            ]));
        } catch (\Throwable) {
            return null;
        }

        $hit = $deepest->searchHits[0] ?? null;

        return $hit === null ? 0 : max(0, $hit->valueObject->getDepth() - $location->getDepth());
    }

    /**
     * Field values of one content, read through the load tool.
     *
     * @return array<string, mixed>
     */
    private function loadContentFields(int $contentId): array
    {
        $result = $this->loadTool->execute(['content_id' => $contentId]);

        return $result->success ? ($result->data['fields'] ?? []) : [];
    }
}
