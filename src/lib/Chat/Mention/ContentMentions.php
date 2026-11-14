<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Chat\Mention;

use Ibexa\Contracts\Core\Repository\Repository;
use Ibexa\Contracts\Core\Repository\Values\Content\Query;
use Ibexa\Contracts\Core\Repository\Values\Content\Query\Criterion;
use Ibexa\Contracts\Core\Repository\Values\Content\Query\SortClause;
use Throwable;

/**
 * The contents the editor can mention in a message with "@": found by part of their name, among the ones their
 * account can read.
 */
final readonly class ContentMentions
{
    public const LIMIT = 8;
    private const CANDIDATES = 40;
    private const MAX_QUERY = 40;

    public function __construct(private Repository $repository)
    {
    }

    /**
     * What may go into a name search: letters, digits and a few separators. Wildcards and the rest are the
     * search engine's own syntax, not what the user typed.
     */
    public static function cleanQuery(string $typed): string
    {
        $kept = preg_replace('/[^\p{L}\p{N} \'\-]+/u', ' ', $typed);

        return mb_substr(trim((string) preg_replace('/\s+/u', ' ', (string) $kept)), 0, self::MAX_QUERY);
    }

    /**
     * Names that start with what was typed come first, then the others, each in alphabetical order.
     *
     * @param list<array{name: string}> $found
     *
     * @return list<array{name: string}>
     */
    public static function rank(array $found, string $query): array
    {
        $needle = mb_strtolower($query);
        usort($found, static function (array $a, array $b) use ($needle): int {
            $first = static fn (array $item): int => str_starts_with(mb_strtolower($item['name']), $needle) ? 0 : 1;

            return [$first($a), mb_strtolower($a['name'])] <=> [$first($b), mb_strtolower($b['name'])];
        });

        return $found;
    }

    /**
     * @return list<array{contentId: int, locationId: int|null, name: string, type: string}>
     */
    public function search(string $typed): array
    {
        $query = self::cleanQuery($typed);
        if ($query === '') {
            return [];
        }

        try {
            $languages = [];
            foreach ($this->repository->getContentLanguageService()->loadLanguages() as $language) {
                $languages[] = $language->getLanguageCode();
            }

            $result = $this->repository->getSearchService()->findContentInfo(
                new Query([
                    'filter' => new Criterion\ContentName('*' . $query . '*'),
                    'sortClauses' => [new SortClause\ContentName()],
                    'limit' => self::CANDIDATES,
                ]),
                ['languages' => $languages, 'useAlwaysAvailable' => true],
            );

            $types = $this->repository->getContentTypeService();
            $found = [];
            foreach ($result->searchHits as $hit) {
                $info = $hit->valueObject;
                $found[] = [
                    'contentId' => $info->getId(),
                    'locationId' => $info->getMainLocationId(),
                    'name' => (string) $info->getName(),
                    'type' => $types->loadContentType($info->contentTypeId)->identifier,
                ];
            }
        } catch (Throwable) {
            return [];
        }

        /** @var list<array{contentId: int, locationId: int|null, name: string, type: string}> $ranked */
        $ranked = self::rank($found, $query);

        return array_slice($ranked, 0, self::LIMIT);
    }
}
