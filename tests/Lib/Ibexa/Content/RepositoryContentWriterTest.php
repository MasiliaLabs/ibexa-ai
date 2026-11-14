<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Tests\Lib\Ibexa\Content;

use Ibexa\Contracts\Core\Repository\ContentService;
use Ibexa\Contracts\Core\Repository\ContentTypeService;
use Ibexa\Core\Base\Exceptions\ContentFieldValidationException;
use Ibexa\Contracts\Core\Repository\LocationService;
use Ibexa\Contracts\Core\Repository\Repository;
use Ibexa\Contracts\Core\Repository\Values\Content\Content;
use Ibexa\Core\Repository\Values\Content\ContentCreateStruct;
use Ibexa\Contracts\Core\Repository\Values\Content\ContentInfo;
use Ibexa\Core\Repository\Values\Content\ContentUpdateStruct;
use Ibexa\Contracts\Core\Repository\Values\Content\Location;
use Ibexa\Contracts\Core\Repository\Values\Content\LocationCreateStruct;
use Ibexa\Contracts\Core\Repository\Values\Content\VersionInfo;
use Ibexa\Contracts\Core\Repository\Values\ContentType\ContentType;
use Ibexa\Contracts\Core\Repository\Values\ContentType\FieldDefinition;
use Ibexa\Core\Base\Exceptions\NotFoundException;
use Masilia\IbexaAi\Ibexa\Content\Write\RepositoryContentWriter;
use Masilia\IbexaAi\Ibexa\Field\Input\FieldInputs;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/** The writer of contents: a draft made, filled and published in one transaction, with what the model said turned into field values. */
final class RepositoryContentWriterTest extends TestCase
{
    private ContentService&MockObject $contents;
    private Repository&MockObject $repository;
    private ContentType&MockObject $type;

    protected function setUp(): void
    {
        $this->contents = $this->createMock(ContentService::class);
        $locations = $this->createMock(LocationService::class);
        $locations->method('newLocationCreateStruct')->willReturnCallback(fn (int $id): LocationCreateStruct => new LocationCreateStruct(['parentLocationId' => $id]));
        $title = $this->createMock(FieldDefinition::class);
        $title->method('getIdentifier')->willReturn('title');
        $title->method('getFieldTypeIdentifier')->willReturn('ibexa_string');
        $this->type = $this->createMock(ContentType::class);
        $this->type->method('getFieldDefinitions')->willReturn(new \Ibexa\Core\Repository\Values\ContentType\FieldDefinitionCollection([$title]));
        $types = $this->createMock(ContentTypeService::class);
        $types->method('loadContentTypeByIdentifier')->willReturnCallback(fn (string $id): ContentType => $id === 'article' ? $this->type : throw new NotFoundException('content type', $id));
        $this->repository = $this->createMock(Repository::class);
        $this->repository->method('getContentService')->willReturn($this->contents);
        $this->repository->method('getLocationService')->willReturn($locations);
        $this->repository->method('getContentTypeService')->willReturn($types);
    }

    private function writer(): RepositoryContentWriter
    {
        return new RepositoryContentWriter($this->repository, new FieldInputs([]));
    }

    private function published(): Content&MockObject
    {
        $info = $this->createMock(ContentInfo::class);
        $info->method('getMainLocation')->willReturn($this->createMock(Location::class));
        $content = $this->createMock(Content::class);
        $content->method('getContentInfo')->willReturn($info);
        $content->method('getVersionInfo')->willReturn($this->createMock(VersionInfo::class));

        return $content;
    }

    public function testAContentTypeThatDoesNotExistIsSaidNotAnsweredWithNothing(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('There is no content type "nope".');

        $this->writer()->create('nope', [2], [], 'eng-GB');
    }

    public function testACreatedContentIsPublishedBelowItsParentsInOneTransaction(): void
    {
        $struct = new ContentCreateStruct(['mainLanguageCode' => 'eng-GB', 'fields' => []]);
        $this->contents->method('newContentCreateStruct')->with($this->type, 'eng-GB')->willReturn($struct);
        $draft = $this->published();
        $this->contents->expects(self::once())->method('createContent')->with($struct, self::callback(static fn (array $parents): bool => count($parents) === 2 && $parents[0]->parentLocationId === 2))->willReturn($draft);
        $this->contents->expects(self::once())->method('publishVersion')->willReturn($this->published());
        $this->repository->expects(self::once())->method('beginTransaction');
        $this->repository->expects(self::once())->method('commit');
        $this->repository->expects(self::never())->method('rollback');

        $created = $this->writer()->create('article', [2, 9], ['title' => 'Hello', 'unknown' => 'ignored'], 'eng-GB');

        self::assertNotNull($created->location);
        self::assertSame(['title'], array_map(static fn ($field): string => $field->fieldDefIdentifier, $struct->fields));
        self::assertSame('Hello', $struct->fields[0]->value);
    }

    public function testWhenIbexaRefusesAValueNothingIsKeptAndTheAnswerIsAMessage(): void
    {
        $this->contents->method('newContentCreateStruct')->willReturn(new ContentCreateStruct(['mainLanguageCode' => 'eng-GB', 'fields' => []]));
        $this->contents->method('createContent')->willThrowException(new ContentFieldValidationException([]));
        $this->repository->expects(self::once())->method('rollback');
        $this->repository->expects(self::never())->method('commit');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to create content of type "article"');

        $this->writer()->create('article', [2], ['title' => ''], 'eng-GB');
    }

    public function testAnUpdateSetsTheGivenFieldsInTheLanguageAndPublishesTheDraft(): void
    {
        $struct = new ContentUpdateStruct(['initialLanguageCode' => 'eng-GB', 'fields' => []]);
        $current = $this->published();
        $current->method('getContentType')->willReturn($this->type);
        $this->contents->method('loadContent')->willReturn($current);
        $this->contents->method('newContentUpdateStruct')->willReturn($struct);
        $this->contents->method('createContentDraft')->willReturn($this->published());
        $this->contents->expects(self::once())->method('updateContent')->with(self::anything(), $struct);
        $published = $this->published();
        $this->contents->expects(self::once())->method('publishVersion')->willReturn($published);
        $this->repository->expects(self::once())->method('commit');

        self::assertSame($published, $this->writer()->update(7, ['title' => 'New'], 'fre-FR'));
        self::assertSame('fre-FR', $struct->fields[0]->languageCode);
    }

    public function testAnUpdateThatMatchesNoFieldSaysWhichFieldsThereAre(): void
    {
        $current = $this->published();
        $current->method('getContentType')->willReturn($this->type);
        $this->contents->method('loadContent')->willReturn($current);
        $this->contents->method('newContentUpdateStruct')->willReturn(new ContentUpdateStruct(['initialLanguageCode' => 'eng-GB', 'fields' => []]));
        $this->contents->expects(self::never())->method('createContentDraft');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Available fields: title');

        $this->writer()->update(7, ['nope' => 'x'], 'eng-GB');
    }

    public function testAnUpdateThatFailsLeavesNoDraftBehind(): void
    {
        $current = $this->published();
        $current->method('getContentType')->willReturn($this->type);
        $this->contents->method('loadContent')->willReturn($current);
        $this->contents->method('newContentUpdateStruct')->willReturn(new ContentUpdateStruct(['initialLanguageCode' => 'eng-GB', 'fields' => []]));
        $this->contents->method('createContentDraft')->willReturn($this->published());
        $this->contents->method('updateContent')->willThrowException(new ContentFieldValidationException([]));
        $this->contents->expects(self::never())->method('publishVersion');
        $this->repository->expects(self::once())->method('rollback');

        $this->expectException(ContentFieldValidationException::class);

        $this->writer()->update(7, ['title' => ''], 'eng-GB');
    }

    public function testADraftIsSavedWithoutBeingPublishedAndHasNoLocationYet(): void
    {
        $this->contents->method('newContentCreateStruct')->willReturn(new ContentCreateStruct(['mainLanguageCode' => 'eng-GB', 'fields' => []]));
        $this->contents->method('createContent')->willReturn($this->published());
        $this->contents->expects(self::never())->method('publishVersion');
        $this->repository->expects(self::once())->method('commit');

        $saved = $this->writer()->create('article', [2], ['title' => 'Later'], 'eng-GB', false);

        self::assertNull($saved->location, 'a draft has no location until it is published');
    }

    public function testAnUpdateCanLeaveItsChangesInADraft(): void
    {
        $current = $this->published();
        $current->method('getContentType')->willReturn($this->type);
        $this->contents->method('loadContent')->willReturn($current);
        $this->contents->method('newContentUpdateStruct')->willReturn(new ContentUpdateStruct(['initialLanguageCode' => 'eng-GB', 'fields' => []]));
        $this->contents->method('createContentDraft')->willReturn($this->published());
        $this->contents->expects(self::once())->method('updateContent');
        $this->contents->expects(self::never())->method('publishVersion');

        $this->writer()->update(7, ['title' => 'Later'], 'eng-GB', false);
    }

    public function testANewTranslationStartsFromTheTranslatableFieldsOfTheBaseLanguageAndWhatIsGivenReplacesThem(): void
    {
        $title = $this->field('title', true);
        $summary = $this->field('summary', true);
        $code = $this->field('code', false);
        $type = $this->createMock(ContentType::class);
        $type->method('getFieldDefinitions')->willReturn(new \Ibexa\Core\Repository\Values\ContentType\FieldDefinitionCollection([$title, $summary, $code]));
        $base = $this->published();
        $base->method('getContentType')->willReturn($type);
        $base->method('getFieldValue')->willReturnCallback(static fn (string $field, ?string $language): \Ibexa\Core\FieldType\TextLine\Value => new \Ibexa\Core\FieldType\TextLine\Value($field . ' in ' . $language));
        $current = $this->published();
        $current->method('getContentType')->willReturn($type);
        $this->contents->method('loadContent')->willReturnCallback(static fn (int $id, ?array $languages = null) => $languages === ['eng-GB'] ? $base : $current);
        $struct = new ContentUpdateStruct(['initialLanguageCode' => 'fre-FR', 'fields' => []]);
        $this->contents->method('newContentUpdateStruct')->willReturn($struct);
        $this->contents->method('createContentDraft')->willReturn($this->published());
        $this->contents->method('publishVersion')->willReturn($this->published());

        $this->writer()->update(7, ['title' => 'Bonjour'], 'fre-FR', true, 'eng-GB');

        $values = [];
        foreach ($struct->fields as $field) {
            $values[$field->fieldDefIdentifier] = is_object($field->value) ? (string) $field->value : $field->value;
        }
        self::assertSame(['title' => 'Bonjour', 'summary' => 'summary in eng-GB'], $values, 'the given field wins, the other translatable one starts from the base, the one that is not translatable is left alone');
    }

    private function field(string $identifier, bool $translatable): FieldDefinition
    {
        $field = $this->createMock(FieldDefinition::class);
        $field->method('getIdentifier')->willReturn($identifier);
        $field->method('getFieldTypeIdentifier')->willReturn('ibexa_string');
        $field->method('isTranslatable')->willReturn($translatable);

        return $field;
    }
}
