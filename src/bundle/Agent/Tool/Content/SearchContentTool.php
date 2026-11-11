<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Agent\Tool\Content;

use Ibexa\Contracts\Core\Repository\Exceptions\NotFoundException;
use Ibexa\Contracts\Core\Repository\Exceptions\UnauthorizedException;
use Ibexa\Contracts\Core\Repository\Repository;
use Ibexa\Contracts\Core\Repository\Values\Content\Query;
use Ibexa\Contracts\Core\Repository\Values\Content\Query\Criterion;
use Ibexa\Contracts\Core\Repository\Values\Content\Query\Criterion\LogicalAnd;
use Ibexa\Contracts\Core\Repository\Values\Content\Query\SortClause;
use Ibexa\Core\Repository\Values\Content\Content;
use Masilia\Bundle\IbexaAi\Agent\Tool\AgentErrorHelper;
use Masilia\Bundle\IbexaAi\Agent\Tool\ToolInterface;
use Masilia\Bundle\IbexaAi\Agent\Tool\ToolName;
use Masilia\Bundle\IbexaAi\Agent\Tool\ToolResult;
use Masilia\Bundle\IbexaAi\Support\AiConstants;
use Psr\Log\LoggerInterface;

readonly class SearchContentTool implements ToolInterface
{
    public function __construct(
        private Repository $repository,
        private LoggerInterface $aiLogger,
    ) {
    }

    public function getName(): string
    {
        return ToolName::SEARCH_CONTENT;
    }

    public function getDescription(): string
    {
        return 'Search for content using criteria like content type, name, full text, and subtree.';
    }

    public function getParameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'content_type' => [
                    'type' => 'string',
                    'description' => 'Content type identifier to filter by',
                ],
                'name' => [
                    'type' => 'string',
                    'description' => 'Search by content name (supports * wildcards, e.g. "homepage" or "about*")',
                ],
                'query' => [
                    'type' => 'string',
                    'description' => 'Full text search query',
                ],
                'subtree_location_id' => [
                    'type' => 'integer',
                    'description' => 'Location ID to search under (subtree)',
                ],
                'limit' => [
                    'type' => 'integer',
                    'description' => 'Max results (default: 10)',
                    'default' => 10,
                ],
                'offset' => [
                    'type' => 'integer',
                    'description' => 'Number of results to skip, for paging (default: 0)',
                    'default' => 0,
                ],
                'language' => [
                    'type' => 'string',
                    'description' => 'Language code (default: eng-GB)',
                    'default' => AiConstants::DEFAULT_LANGUAGE_CODE,
                ],
            ],
        ];
    }

    public function execute(array $params): ToolResult
    {
        try {
            $searchService = $this->repository->getSearchService();
            $locationService = $this->repository->getLocationService();

            $criteria = [];

            // AND filters
            if (!empty($params['content_type'])) {
                $criteria[] = new Criterion\ContentTypeIdentifier([$params['content_type']]);
            }

            if (!empty($params['subtree_location_id'])) {
                try {
                    $location = $locationService->loadLocation((int) $params['subtree_location_id']);
                    $criteria[] = new Criterion\Subtree($location->pathString);
                } catch (NotFoundException) {
                    return ToolResult::error(
                        sprintf('Location %d not found', (int) $params['subtree_location_id']),
                    );
                } catch (UnauthorizedException $e) {
                    return ToolResult::error(
                        sprintf('Access denied to location %d', (int) $params['subtree_location_id']),
                    );
                }
            }

            // OR filter: name OR fulltext
            $orCriteria = [];
            if (!empty($params['name'])) {
               $orCriteria[] = new Criterion\ContentName($params['name']);
            }
            if (!empty($params['query'])) {
                $orCriteria[] = new Criterion\FullText($params['query']);
            }
            if (!empty($orCriteria)) {
                $criteria[] = new Criterion\LogicalOr($orCriteria);
            }


            $query = new Query([
                'query' => !empty($criteria) ? new LogicalAnd($criteria) : null,
                'limit' => $params['limit'] ?? 10,
                'offset' => max(0, (int) ($params['offset'] ?? 0)),
                // A stable order, so that paging with offset never skips or repeats a content.
                'sortClauses' => [new SortClause\ContentId()],
            ]);

            // Search in every language of the repository, not only those of the current siteaccess:
            // a content that exists only in French must still be found from an English-first siteaccess.
            $languages = [];
            foreach ($this->repository->getContentLanguageService()->loadLanguages() as $language) {
                $languages[] = $language->languageCode;
            }

            $searchResult = $searchService->findContent($query, ['languages' => $languages, 'useAlwaysAvailable' => true]);


            $results = [];
            foreach ($searchResult->searchHits as $hit) {
                /** @var   Content  $content */
                $content = $hit->valueObject;

                $results[] = [
                    'content_id' => $content->id,
                    'content_type' => $content->getContentType()->identifier,
                    'name' => $content->contentInfo->name,
                    'remote_id' => $content->contentInfo->remoteId,
                    'languages' => array_values($content->getVersionInfo()->languageCodes),
                    'visible' => $this->isVisible($content),
                ];
            }

            return ToolResult::ok(
                sprintf('Found %d results', $searchResult->totalCount),
                [
                    'count' => $searchResult->totalCount,
                    'results' => $results
                ]
            );
        } catch (\Throwable $e) {
            return AgentErrorHelper::handle($this->aiLogger, $e, 'search content');
        }
    }

    /**
     * Whether visitors can see the content: it is not hidden itself, and its main location is neither
     * hidden nor under a hidden location. Null when that cannot be read (e.g. no location).
     */
    private function isVisible(Content $content): ?bool
    {
        if ($content->contentInfo->isHidden) {
            return false;
        }

        $mainLocationId = $content->contentInfo->mainLocationId;
        if ($mainLocationId === null) {
            return null;
        }

        try {
            $location = $this->repository->getLocationService()->loadLocation($mainLocationId);
        } catch (\Throwable) {
            return null;
        }

        return !$location->hidden && !$location->invisible;
    }
}
