<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Tests\Agent\Tool;

use Ibexa\Contracts\Core\Repository\Repository;
use Ibexa\Contracts\Core\Repository\TrashService;
use Ibexa\Contracts\Core\Repository\Values\Content\Query;
use Ibexa\Core\Repository\Values\Content\Location;
use Ibexa\Core\Repository\Values\Content\TrashItem;
use Ibexa\Contracts\Core\Repository\Values\Content\Trash\SearchResult as TrashSearchResult;
use Masilia\Bundle\IbexaAi\Agent\Tool\TrashRestorer;
use PHPUnit\Framework\TestCase;

/**
 * The trash cannot be searched by content id (NotImplementedException): items are read page by page.
 */
final class TrashRestorerTest extends TestCase
{
    public function testItFindsTheWantedItemsAcrossPagesAndRecoversOnlyThose(): void
    {
        $page1 = array_map(fn (int $id): TrashItem => $this->item($id), range(1, 100));
        $page2 = [$this->item(1341), $this->item(2000)];

        $recovered = [];
        $trash = $this->createMock(TrashService::class);
        $trash->method('findTrashItems')->willReturnCallback(
            fn (Query $query): TrashSearchResult => new TrashSearchResult([
                'items' => $query->offset === 0 ? $page1 : $page2,
                'totalCount' => 102,
            ]),
        );
        $trash->method('recover')->willReturnCallback(function (TrashItem $item) use (&$recovered) {
            $recovered[] = $item->contentId;

            return new Location(['id' => $item->id]);
        });

        $repository = $this->createMock(Repository::class);
        $repository->method('getTrashService')->willReturn($trash);

        $restored = (new TrashRestorer($repository))->restore([1341, 999999]);

        self::assertSame([1341], $restored);
        self::assertSame([1341], $recovered, 'Only the wanted content is recovered.');
    }

    public function testItDoesNothingWhenNoIdIsGiven(): void
    {
        $trash = $this->createMock(TrashService::class);
        $trash->expects(self::never())->method('findTrashItems');
        $repository = $this->createMock(Repository::class);
        $repository->method('getTrashService')->willReturn($trash);

        self::assertSame([], (new TrashRestorer($repository))->restore([]));
    }

    private function item(int $contentId): TrashItem
    {
        return new TrashItem(['id' => $contentId + 10000, 'contentInfo' => new \Ibexa\Contracts\Core\Repository\Values\Content\ContentInfo(['id' => $contentId])]);
    }
}
