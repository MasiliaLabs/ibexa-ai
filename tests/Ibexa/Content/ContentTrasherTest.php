<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Tests\Ibexa\Content;

use Ibexa\Contracts\Core\Repository\LanguageService as ContentLanguageService;
use Ibexa\Contracts\Core\Repository\ContentService;
use Ibexa\Contracts\Core\Repository\LocationService;
use Ibexa\Contracts\Core\Repository\Repository;
use Ibexa\Contracts\Core\Repository\TrashService;
use Ibexa\Contracts\Core\Repository\Values\Content\Content;
use Ibexa\Contracts\Core\Repository\Values\Content\ContentInfo;
use Ibexa\Contracts\Core\Repository\Values\Content\Location;
use Ibexa\Core\Repository\Values\Content\Relation;
use Ibexa\Core\FieldType\Relation\Value as ObjectRelationValue;
use Ibexa\Core\FieldType\RelationList\Value as ObjectRelationListValue;
use Masilia\Contracts\IbexaAi\Content\ContentWriter;
use Masilia\Bundle\IbexaAi\Ibexa\Content\Trash\ContentTrasher;
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
    /** A relation field of another content, given the ids it lists. */
    private function source(int $id, string $field, ObjectRelationValue|ObjectRelationListValue $value): Content
    {
        $info = $this->createMock(ContentInfo::class);
        $info->method('getId')->willReturn($id);
        $info->method('getName')->willReturn('Source ' . $id);
        $content = $this->createMock(Content::class);
        $content->method('getContentInfo')->willReturn($info);
        $content->method('getFieldValue')->willReturnCallback(static fn (string $f) => $f === $field ? $value : null);

        return $content;
    }

    /** @param list<int> $ids */
    private function relationList(array $ids): ObjectRelationListValue
    {
        return new ObjectRelationListValue($ids);
    }

    private function reverse(int $sourceId, string $field): Relation
    {
        $source = $this->createMock(ContentInfo::class);
        $source->method('getId')->willReturn($sourceId);

        return new Relation(['type' => Relation::FIELD, 'sourceFieldDefinitionIdentifier' => $field, 'sourceContentInfo' => $source]);
    }

    public function testTheTrashedContentIsRemovedFromTheRelationListsThatHoldItAndTheMainLocationIsTrashed(): void
    {
        $info = $this->createMock(ContentInfo::class);
        $info->method('getMainLocationId')->willReturn(2000);
        $content = $this->createMock(Content::class);
        $content->method('getContentInfo')->willReturn($info);

        $contents = $this->createMock(ContentService::class);
        $contents->method('loadContent')->willReturnCallback(fn (int $id) => $id === 1353 ? $content : $this->source($id, 'blocks', $this->relationList([1353, 9])));
        $contents->method('loadContentInfo')->willReturn($info);
        $contents->method('loadReverseRelations')->willReturn([$this->reverse(77, 'blocks')]);

        $location = $this->createMock(Location::class);
        $locations = $this->createMock(LocationService::class);
        $locations->method('loadLocation')->with(2000)->willReturn($location);
        $trash = $this->createMock(TrashService::class);
        $trash->expects(self::once())->method('trash')->with($location);
        $languages = $this->createMock(ContentLanguageService::class);
        $languages->method('getDefaultLanguageCode')->willReturn('eng-GB');

        $repository = $this->createMock(Repository::class);
        $repository->method('getContentService')->willReturn($contents);
        $repository->method('getLocationService')->willReturn($locations);
        $repository->method('getTrashService')->willReturn($trash);
        $repository->method('getContentLanguageService')->willReturn($languages);

        $updater = $this->createMock(ContentWriter::class);
        $updater->expects(self::once())->method('update')->with(77, ['blocks' => [9]], 'eng-GB');

        $trashed = (new ContentTrasher($repository, $updater, $logger = new RecordingLogger()))->trash(1353);

        self::assertSame(['content_id' => 1353, 'location_id' => 2000, 'trashed' => true, 'unlinked_from' => ['77:Source 77'], 'trashed_assets' => []], $trashed);
        self::assertSame([], $logger->records, 'nothing failed on the way');
    }

    public function testASingleRelationPointingAtTheTrashedContentIsEmptied(): void
    {
        $info = $this->createMock(ContentInfo::class);
        $info->method('getMainLocationId')->willReturn(3);
        $content = $this->createMock(Content::class);
        $content->method('getContentInfo')->willReturn($info);
        $single = new ObjectRelationValue(1353);

        $contents = $this->createMock(ContentService::class);
        $contents->method('loadContent')->willReturnCallback(fn (int $id) => $id === 1353 ? $content : $this->source($id, 'image', $single));
        $contents->method('loadContentInfo')->willReturn($info);
        $contents->method('loadReverseRelations')->willReturn([$this->reverse(88, 'image')]);

        $locations = $this->createMock(LocationService::class);
        $locations->method('loadLocation')->willReturn($this->createMock(Location::class));
        $languages = $this->createMock(ContentLanguageService::class);
        $languages->method('getDefaultLanguageCode')->willReturn('eng-GB');
        $repository = $this->createMock(Repository::class);
        $repository->method('getContentService')->willReturn($contents);
        $repository->method('getLocationService')->willReturn($locations);
        $repository->method('getTrashService')->willReturn($this->createMock(TrashService::class));
        $repository->method('getContentLanguageService')->willReturn($languages);

        $updater = $this->createMock(ContentWriter::class);
        $updater->expects(self::once())->method('update')->with(88, ['image' => null], 'eng-GB');

        (new ContentTrasher($repository, $updater, $logger = new RecordingLogger()))->trash(1353);
        self::assertSame([], $logger->records, 'nothing failed on the way');
    }
}
