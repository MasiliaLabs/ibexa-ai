<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Tests\Ibexa\Content;

use Ibexa\Contracts\Core\Repository\LocationService;
use Ibexa\Contracts\Core\Repository\Repository;
use Masilia\Bundle\IbexaAi\Ibexa\Content\Read\ContentLoader;
use Masilia\Bundle\IbexaAi\Ibexa\Content\Read\ContentSearch;
use Masilia\Bundle\IbexaAi\Ibexa\Content\Read\LocationBrowser;
use PHPUnit\Framework\TestCase;

/** The browsing of a location: the page of its contents, and the filters the index cannot apply, over the whole subtree. */
final class LocationBrowserTest extends TestCase
{
    /** @param list<array<string, mixed>> $results */
    private function browser(array $results, ?int $count = null): LocationBrowser
    {
        $search = $this->createMock(ContentSearch::class);
        $search->method('search')->willReturnCallback(static function (array $filters) use ($results, $count): array {
            $offset = (int) ($filters['offset'] ?? 0);
            $limit = (int) ($filters['limit'] ?? 10);

            return ['count' => $count ?? count($results), 'results' => array_slice($results, $offset, $limit)];
        });

        // No location to read the depth from: the depth is then unknown (null), which the browse must tolerate.
        $locations = $this->createMock(LocationService::class);
        $locations->method('loadLocation')->willThrowException(new \RuntimeException('no location'));
        $repository = $this->createMock(Repository::class);
        $repository->method('getLocationService')->willReturn($locations);

        return new LocationBrowser($repository, $search, $this->createMock(ContentLoader::class));
    }

    private function item(int $id, array $languages = ['eng-GB'], ?bool $visible = true): array
    {
        return ['content_id' => $id, 'content_type' => 'page', 'name' => "Page $id", 'remote_id' => null, 'languages' => $languages, 'visible' => $visible];
    }

    public function testAPageOfTheSubtreeIsReturnedWithItsTotal(): void
    {
        $browsed = $this->browser([$this->item(1), $this->item(2), $this->item(3)])->browse(['location_id' => 5, 'limit' => 2]);

        self::assertSame('Found 3 items under location 5 (2 returned from offset 0)', $browsed['message']);
        self::assertSame(3, $browsed['data']['total']);
        self::assertSame([1, 2], array_column($browsed['data']['items'], 'content_id'));
        self::assertNull($browsed['data']['max_depth_below'], 'the depth is unknown when the location cannot be read');
    }

    public function testAMissingTranslationIsFoundOverTheWholeSubtree(): void
    {
        $results = [$this->item(1, ['eng-GB']), $this->item(2, ['eng-GB', 'fre-FR']), $this->item(3, ['eng-GB']), $this->item(4, ['fre-FR'])];

        $browsed = $this->browser($results)->browse(['location_id' => 5, 'missing_language' => 'fre-FR']);

        self::assertSame([1, 3], array_column($browsed['data']['items'], 'content_id'));
        self::assertSame(2, $browsed['data']['total'], 'the count is of the matches, not of the page');
        self::assertSame('2 contents under location 5 have no fre-FR translation (2 returned)', $browsed['message']);
        self::assertSame('fre-FR', $browsed['data']['missing_language']);
    }

    public function testTheHiddenContentsAreFoundOnlyWhenAskedFor(): void
    {
        $results = [$this->item(1, visible: true), $this->item(2, visible: false), $this->item(3, visible: null)];

        $browsed = $this->browser($results)->browse(['location_id' => 5, 'only_hidden' => true]);

        self::assertSame([2], array_column($browsed['data']['items'], 'content_id'));
        self::assertSame('1 contents under location 5 are not visible on the site (1 returned)', $browsed['message']);
        self::assertTrue($browsed['data']['only_hidden']);
    }

    public function testAWrongOrMissingLocationIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('browse_location requires a valid location_id');

        $this->browser([])->browse(['location_id' => '5']);
    }

    public function testTheLimitIsBetweenOneAndTheMaximum(): void
    {
        $results = array_map(fn (int $id): array => $this->item($id), range(1, 5));

        self::assertCount(1, $this->browser($results)->browse(['location_id' => 5, 'limit' => 0])['data']['items']);
        self::assertCount(5, $this->browser($results)->browse(['location_id' => 5, 'limit' => 999])['data']['items']);
    }
}
