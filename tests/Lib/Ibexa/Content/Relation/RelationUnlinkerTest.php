<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Tests\Lib\Ibexa\Content\Relation;

use Ibexa\Contracts\Core\Repository\ContentService;
use Ibexa\Contracts\Core\Repository\LanguageService as ContentLanguageService;
use Ibexa\Contracts\Core\Repository\Repository;
use Ibexa\Contracts\Core\Repository\Values\Content\Content;
use Ibexa\Contracts\Core\Repository\Values\Content\ContentInfo;
use Ibexa\Core\FieldType\Relation\Value as ObjectRelationValue;
use Ibexa\Core\FieldType\RelationList\Value as ObjectRelationListValue;
use Ibexa\Core\Repository\Values\Content\Relation;
use Masilia\Contracts\IbexaAi\Content\ContentWriter;
use Masilia\IbexaAi\Ibexa\Content\Relation\RelationUnlinker;
use Masilia\IbexaAi\Tests\Lib\Ibexa\Content\RecordingLogger;
use PHPUnit\Framework\TestCase;

/** Removing a content from the relation fields that hold it: the explicit step the trash of the admin leaves to the editor. */
final class RelationUnlinkerTest extends TestCase
{
    /** A relation field of another content, given the value it holds. */
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

    private function reverse(int $sourceId, string $field, int $type = Relation::FIELD): Relation
    {
        $source = $this->createMock(ContentInfo::class);
        $source->method('getId')->willReturn($sourceId);

        return new Relation(['type' => $type, 'sourceFieldDefinitionIdentifier' => $field, 'sourceContentInfo' => $source]);
    }

    /** @param list<Relation> $reverse */
    private function unlinker(array $reverse, callable $sources, ContentWriter $writer, RecordingLogger $logger): RelationUnlinker
    {
        $info = $this->createMock(ContentInfo::class);
        $contents = $this->createMock(ContentService::class);
        $contents->method('loadContent')->willReturnCallback($sources);
        $contents->method('loadContentInfo')->willReturn($info);
        $contents->method('loadReverseRelations')->willReturn($reverse);
        $languages = $this->createMock(ContentLanguageService::class);
        $languages->method('getDefaultLanguageCode')->willReturn('eng-GB');
        $repository = $this->createMock(Repository::class);
        $repository->method('getContentService')->willReturn($contents);
        $repository->method('getContentLanguageService')->willReturn($languages);

        return new RelationUnlinker($repository, $writer, $logger);
    }

    public function testTheContentIsRemovedFromTheRelationListsThatHoldItAndTheOthersStay(): void
    {
        $writer = $this->createMock(ContentWriter::class);
        $writer->expects(self::once())->method('update')->with(77, ['blocks' => [9]], 'eng-GB');
        $unlinker = $this->unlinker([$this->reverse(77, 'blocks')], fn (int $id) => $this->source($id, 'blocks', new ObjectRelationListValue([1353, 9])), $writer, $logger = new RecordingLogger());

        self::assertSame(['content_id' => 1353, 'unlinked_from' => ['77:Source 77'], 'failed' => []], $unlinker->unlink(1353));
        self::assertSame([], $logger->records, 'nothing failed on the way');
    }

    public function testASingleRelationPointingAtTheContentIsEmptied(): void
    {
        $writer = $this->createMock(ContentWriter::class);
        $writer->expects(self::once())->method('update')->with(88, ['image' => null], 'eng-GB');
        $unlinker = $this->unlinker([$this->reverse(88, 'image')], fn (int $id) => $this->source($id, 'image', new ObjectRelationValue(1353)), $writer, new RecordingLogger());

        self::assertSame(['88:Source 88'], $unlinker->unlink(1353)['unlinked_from']);
    }

    public function testOnlyTheRelationFieldsCountNotTheLinksOfARichText(): void
    {
        $writer = $this->createMock(ContentWriter::class);
        $writer->expects(self::never())->method('update');
        $unlinker = $this->unlinker([$this->reverse(5, 'body', Relation::LINK), $this->reverse(6, 'body', Relation::EMBED)], fn (int $id) => $this->source($id, 'body', new ObjectRelationValue(1353)), $writer, new RecordingLogger());

        self::assertSame([], $unlinker->sources(1353));
        self::assertSame(['content_id' => 1353, 'unlinked_from' => [], 'failed' => []], $unlinker->unlink(1353));
    }

    public function testAContentThatCannotBeChangedIsSaidFailedNotLeftOut(): void
    {
        $writer = $this->createMock(ContentWriter::class);
        $writer->method('update')->willThrowException(new \RuntimeException('not allowed'));
        $unlinker = $this->unlinker([$this->reverse(77, 'blocks')], fn (int $id) => $this->source($id, 'blocks', new ObjectRelationListValue([1353])), $writer, $logger = new RecordingLogger());

        $done = $unlinker->unlink(1353);

        self::assertSame([], $done['unlinked_from']);
        self::assertSame(['77:Source 77'], $done['failed']);
        self::assertNotSame([], $logger->records);
    }
}
