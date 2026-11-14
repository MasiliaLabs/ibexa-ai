<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Ibexa\Content\Read;

use Ibexa\Contracts\Core\Repository\Exceptions\NotFoundException;
use Ibexa\Contracts\Core\Repository\Exceptions\UnauthorizedException;
use Ibexa\Contracts\Core\Repository\Repository;
use Ibexa\Contracts\Core\Repository\Values\Content\Query;
use Ibexa\Contracts\Core\Repository\Values\Content\Query\Criterion;
use Ibexa\Contracts\Core\Repository\Values\Content\Query\Criterion\LogicalAnd;
use Ibexa\Contracts\Core\Repository\Values\Content\Query\SortClause;
use Ibexa\Core\Repository\Values\Content\Content;
use Masilia\Bundle\IbexaAi\Support\AiConstants;

/**
 * Finds contents by type, name, full text and subtree, in every language of the repository, for the search capability and
 * the browsing of a location. It only reads.
 */
readonly class ContentSearch
{
    public function __construct(private Repository $repository)
    {
    }

    /**
     * @param array<string, mixed> $params content_type, name, query, subtree_location_id, limit, offset
     *
     * @return array{count: int, results: list<array<string, mixed>>}
     *
     * @throws \RuntimeException When the subtree does not exist or is not readable
     */
    public function search(array $params): array
    {
        $criteria = [];
        if (!empty($params['content_type'])) {
            $criteria[] = new Criterion\ContentTypeIdentifier([$params['content_type']]);
        }
        if (!empty($params['subtree_location_id'])) {
            $criteria[] = new Criterion\Subtree($this->subtreePath((int) $params['subtree_location_id']));
        }

        // One of name or full text is enough.
        $orCriteria = [];
        if (!empty($params['name'])) {
            $orCriteria[] = new Criterion\ContentName($params['name']);
        }
        if (!empty($params['query'])) {
            $orCriteria[] = new Criterion\FullText($params['query']);
        }
        if ($orCriteria !== []) {
            $criteria[] = new Criterion\LogicalOr($orCriteria);
        }

        $query = new Query([
            'query' => $criteria !== [] ? new LogicalAnd($criteria) : null,
            'limit' => $params['limit'] ?? 10,
            'offset' => max(0, (int) ($params['offset'] ?? 0)),
            // A stable order, so that paging with offset never skips or repeats a content.
            'sortClauses' => [new SortClause\ContentId()],
        ]);

        // In every language of the repository, not only those of the current siteaccess: a content that exists only in
        // French must still be found from an English-first siteaccess.
        $languages = [];
        foreach ($this->repository->getContentLanguageService()->loadLanguages() as $language) {
            $languages[] = $language->getLanguageCode();
        }
        $found = $this->repository->getSearchService()->findContent($query, ['languages' => $languages, 'useAlwaysAvailable' => true]);

        $results = [];
        foreach ($found->searchHits as $hit) {
            /** @var Content $content */
            $content = $hit->valueObject;
            $results[] = [
                'content_id' => $content->getId(),
                'content_type' => $content->getContentType()->getIdentifier(),
                'name' => $content->getContentInfo()->getName(),
                'remote_id' => $content->getContentInfo()->remoteId,
                'languages' => array_values($content->getVersionInfo()->getLanguageCodes()),
                'visible' => $this->isVisible($content),
            ];
        }

        return ['count' => $found->totalCount, 'results' => $results];
    }

    private function subtreePath(int $locationId): string
    {
        try {
            return $this->repository->getLocationService()->loadLocation($locationId)->getPathString();
        } catch (NotFoundException) {
            throw new \RuntimeException(sprintf('Location %d not found', $locationId));
        } catch (UnauthorizedException) {
            throw new \RuntimeException(sprintf('Access denied to location %d', $locationId));
        }
    }

    /**
     * Whether visitors can see the content: it is not hidden itself, and its main location is neither hidden nor under a
     * hidden location. Null when that cannot be read (e.g. no location).
     */
    private function isVisible(Content $content): ?bool
    {
        if ($content->getContentInfo()->isHidden()) {
            return false;
        }

        $mainLocationId = $content->getContentInfo()->getMainLocationId();
        if ($mainLocationId === null) {
            return null;
        }

        try {
            $location = $this->repository->getLocationService()->loadLocation($mainLocationId);
        } catch (\Throwable) {
            return null;
        }

        return !$location->isHidden() && !$location->isInvisible();
    }
}
