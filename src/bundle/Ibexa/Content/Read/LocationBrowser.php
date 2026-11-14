<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Ibexa\Content\Read;

use Ibexa\Contracts\Core\Repository\Repository;
use Ibexa\Contracts\Core\Repository\Values\Content\LocationQuery;
use Ibexa\Contracts\Core\Repository\Values\Content\Query;
use Ibexa\Contracts\Core\Repository\Values\Content\Query\Criterion;
use Ibexa\Contracts\Core\Repository\Values\Content\Query\SortClause;
use InvalidArgumentException;
use Throwable;

/**
 * Lists what is under a location: its contents, a page at a time, with the filters the model cannot apply itself (a missing
 * translation, the hidden ones) applied over the whole subtree, so that the count is exact. It only reads.
 */
readonly class LocationBrowser
{
    public const DEFAULT_LIMIT = 50;
    public const MAX_LIMIT = 200;
    /** The subtree is scanned a page at a time, up to this many contents. */
    private const SCAN_PAGE = 200;
    private const SCAN_MAX = 5000;

    public function __construct(
        private Repository $repository,
        private ContentSearch $search,
        private ContentLoader $loader,
    ) {
    }

    /**
     * @param array<string, mixed> $arguments location_id (required), query, name, content_type, missing_language,
     *                                        only_hidden, limit, offset, include_fields
     *
     * @return array{message: string, data: array<string, mixed>}
     *
     * @throws InvalidArgumentException When location_id is missing or not an id
     * @throws \RuntimeException When the location cannot be searched
     */
    public function browse(array $arguments): array
    {
        $locationId = $arguments['location_id'] ?? null;
        if (!is_int($locationId) || $locationId <= 0) {
            throw new InvalidArgumentException('browse_location requires a valid location_id');
        }

        $limit = max(1, min(self::MAX_LIMIT, (int) ($arguments['limit'] ?? self::DEFAULT_LIMIT)));
        $offset = max(0, (int) ($arguments['offset'] ?? 0));
        $missingLanguage = is_string($arguments['missing_language'] ?? null) ? trim($arguments['missing_language']) : '';
        $onlyHidden = ($arguments['only_hidden'] ?? false) === true;

        $filters = ['subtree_location_id' => $locationId];
        foreach (['query', 'name', 'content_type'] as $key) {
            if (is_string($arguments[$key] ?? null) && $arguments[$key] !== '') {
                $filters[$key] = $arguments[$key];
            }
        }

        // The filters that the search index cannot answer are applied over the whole subtree.
        $keep = null;
        if ($missingLanguage !== '' || $onlyHidden) {
            $keep = static fn (array $item): bool => ($missingLanguage === '' || !in_array($missingLanguage, $item['languages'] ?? [], true))
                && (!$onlyHidden || ($item['visible'] ?? null) === false);
        }

        $page = $keep !== null
            ? $this->scan($keep, $filters, $limit, $offset)
            : $this->page($filters, $limit, $offset);

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
            if (($arguments['include_fields'] ?? false) === true && ($item['content_id'] ?? null) !== null) {
                $entry['fields'] = $this->fieldsOf((int) $item['content_id']);
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

        if ($missingLanguage !== '') {
            $data['missing_language'] = $missingLanguage;
        }
        if ($onlyHidden) {
            $data['only_hidden'] = true;
        }
        if ($page['incomplete'] ?? false) {
            $data['incomplete'] = sprintf('Only the first %d contents of the subtree were checked.', self::SCAN_MAX);
        }

        $conditions = self::conditions($missingLanguage, $onlyHidden);
        $message = $conditions !== []
            ? sprintf('%d contents under location %d %s (%d returned)', $total, $locationId, implode(' and ', $conditions), count($items))
            : sprintf('Found %d items under location %d (%d returned from offset %d)', $total, $locationId, count($items), $offset);

        return ['message' => $message, 'data' => $data];
    }

    /**
     * What the filters ask for, in words for the message: "have no eng-GB translation", "are not visible on the site".
     *
     * @return list<string>
     */
    private static function conditions(string $missingLanguage, bool $onlyHidden): array
    {
        $conditions = [];
        if ($missingLanguage !== '') {
            $conditions[] = sprintf('have no %s translation', $missingLanguage);
        }
        if ($onlyHidden) {
            $conditions[] = 'are not visible on the site';
        }

        return $conditions;
    }

    /**
     * One page of the search, with its total.
     *
     * @param array<string, mixed> $filters
     *
     * @return array{results: list<array<string, mixed>>, total: int}
     */
    private function page(array $filters, int $limit, int $offset): array
    {
        $found = $this->search->search($filters + ['limit' => $limit, 'offset' => $offset]);

        return ['results' => $found['results'], 'total' => $found['count']];
    }

    /**
     * Scans the whole subtree and keeps the contents accepted by $keep. The count is exact (up to SCAN_MAX contents); only the
     * requested page of matches is returned.
     *
     * @param callable(array<string, mixed>): bool $keep
     * @param array<string, mixed>                 $filters
     *
     * @return array{results: list<array<string, mixed>>, total: int, incomplete: bool}
     */
    private function scan(callable $keep, array $filters, int $limit, int $offset): array
    {
        $matches = [];
        $scanned = 0;
        $total = null;
        do {
            $page = $this->page($filters, self::SCAN_PAGE, $scanned);
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
        } catch (Throwable) {
            return null;
        }

        $hit = $deepest->searchHits[0] ?? null;

        return $hit === null ? 0 : max(0, $hit->valueObject->getDepth() - $location->getDepth());
    }

    /**
     * The field values of one content (empty when they cannot be read).
     *
     * @return array<string, mixed>
     */
    private function fieldsOf(int $contentId): array
    {
        try {
            return $this->loader->load(['content_id' => $contentId])['fields'];
        } catch (Throwable) {
            return [];
        }
    }
}
