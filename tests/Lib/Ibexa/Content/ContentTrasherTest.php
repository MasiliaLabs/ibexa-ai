<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Tests\Lib\Ibexa\Content;

use Ibexa\Contracts\Core\Repository\ContentService;
use Ibexa\Contracts\Core\Repository\LocationService;
use Ibexa\Contracts\Core\Repository\Repository;
use Ibexa\Contracts\Core\Repository\TrashService;
use Ibexa\Contracts\Core\Repository\Values\Content\Content;
use Ibexa\Contracts\Core\Repository\Values\Content\ContentInfo;
use Ibexa\Contracts\Core\Repository\Values\Content\Location;
use Masilia\IbexaAi\Ibexa\Content\Trash\ContentTrasher;
use Psr\Log\AbstractLogger;
use Stringable;
use PHPUnit\Framework\TestCase;

/** Keeps what was logged, so that a test can say nothing failed on the way. */
final class RecordingLogger extends AbstractLogger
{
    /** @var list<string> */
    public array $records = [];

    public function log($level, string|Stringable $message, array $context = []): void
    {
        $this->records[] = sprintf('%s: %s %s', $level, $message, $context['message'] ?? '');
    }
}

final class ContentTrasherTest extends TestCase
{
    public function testTheMainLocationIsTrashedAndNoOtherContentIsChanged(): void
    {
        $info = $this->createMock(ContentInfo::class);
        $info->method('getMainLocationId')->willReturn(2000);
        $content = $this->createMock(Content::class);
        $content->method('getContentInfo')->willReturn($info);

        $contents = $this->createMock(ContentService::class);
        $contents->method('loadContent')->willReturn($content);
        // The contents that point at it are never loaded to be changed: the admin only warns about them.
        $contents->expects(self::never())->method('createContentDraft');
        $contents->expects(self::never())->method('updateContent');

        $location = $this->createMock(Location::class);
        $locations = $this->createMock(LocationService::class);
        $locations->method('loadLocation')->with(2000)->willReturn($location);
        $trash = $this->createMock(TrashService::class);
        $trash->expects(self::once())->method('trash')->with($location);

        $repository = $this->createMock(Repository::class);
        $repository->method('getContentService')->willReturn($contents);
        $repository->method('getLocationService')->willReturn($locations);
        $repository->method('getTrashService')->willReturn($trash);

        self::assertSame(
            ['content_id' => 1353, 'location_id' => 2000, 'trashed' => true, 'trashed_assets' => []],
            (new ContentTrasher($repository))->trash(1353),
        );
    }
}
