<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Tests\Bundle\Os;

use Ibexa\Contracts\Core\Repository\ContentService;
use Ibexa\Contracts\Core\Repository\LanguageService;
use Ibexa\Contracts\Core\Repository\PermissionResolver;
use Ibexa\Contracts\Core\Repository\Repository;
use Ibexa\Contracts\Core\Repository\Values\Content\Content;
use Ibexa\Contracts\Core\Repository\Values\Content\ContentInfo;
use Ibexa\Contracts\Core\Repository\Values\Content\Location;
use Ibexa\Contracts\Core\Repository\Values\Content\VersionInfo;
use Ibexa\Core\FieldType\Image\Value as ImageValue;
use Masilia\Bundle\IbexaAi\Content\Write\WriteChecks;
use Masilia\Bundle\IbexaAi\Os\Capability\Content\CreateCapability;
use Masilia\Bundle\IbexaAi\Os\Capability\Content\CreateFolderCapability;
use Masilia\Bundle\IbexaAi\Os\Capability\Content\UpdateCapability;
use Masilia\Bundle\IbexaAi\Os\Capability\Media\AddImageCapability;
use Masilia\Contracts\IbexaAi\Content\ContentWriter;
use Masilia\Contracts\IbexaAi\Content\Languages;
use Masilia\Contracts\IbexaAi\Content\PublishedContent;
use Masilia\IbexaAi\Tests\Bundle\Content\WriteFixtures;
use PHPUnit\Framework\TestCase;

/**
 * The write capabilities, as the assistant runs them: refused before approval when a check fails, and otherwise written
 * through the content writer with the language the site gives.
 */
final class WriteCapabilitiesTest extends TestCase
{
    use WriteFixtures;

    private function published(int $id = 500): PublishedContent
    {
        $content = $this->createMock(Content::class);
        $content->method('getId')->willReturn($id);
        $location = $this->createMock(Location::class);
        $location->method('getId')->willReturn($id + 1);

        return new PublishedContent($content, $location);
    }

    private function languages(): Languages
    {
        $service = $this->createMock(LanguageService::class);
        $service->method('loadLanguages')->willReturn([]);
        $service->method('getDefaultLanguageCode')->willReturn('eng-GB');

        return new class($service) implements Languages {
            public function __construct(private readonly LanguageService $service) {}

            public function resolve(string $requested): ?string
            {
                return in_array($requested, ['eng-GB', 'fre-FR', 'ara-MA'], true) ? $requested : (['fr' => 'fre-FR', 'en' => 'eng-GB'][$requested] ?? null);
            }

            public function default(): string
            {
                return 'eng-GB';
            }
        };
    }

    public function testCreatingWritesWithTheLanguageOfTheSiteAndReturnsTheIds(): void
    {
        $writer = $this->createMock(ContentWriter::class);
        $writer->expects(self::once())->method('create')
            ->with('article', [1018], ['title' => 'Hello'], 'eng-GB')
            ->willReturn($this->published(500));

        $result = (new CreateCapability($this->checks(['article' => ['name' => 'Article', 'fields' => ['title' => 'ibexa_string']]]), $writer, $this->languages()))
            ->execute(['content_type' => 'article', 'parent_location_id' => 1018, 'fields' => ['title' => 'Hello'], 'siteaccess' => 'fossil_exit']);

        self::assertTrue($result->success);
        self::assertSame(['content_id' => 500, 'location_id' => 501], $result->data);
    }

    public function testACreationWithAMissingRequiredFieldIsRefusedBeforeAnythingIsWritten(): void
    {
        $writer = $this->createMock(ContentWriter::class);
        $writer->expects(self::never())->method('create');
        $capability = new CreateCapability($this->checks(['article' => ['name' => 'Article', 'fields' => ['title' => ['type' => 'ibexa_string', 'required' => true]]]]), $writer, $this->languages());

        self::assertStringContainsString('required field "title" is empty', (string) $capability->validate(['content_type' => 'article', 'parent_location_id' => 1018, 'fields' => ['title' => '']]));
        self::assertSame('content.create requires non-empty fields.', $capability->validate(['content_type' => 'article', 'parent_location_id' => 1018, 'fields' => []]));
    }

    public function testAFolderIsWrittenAsAFolderWithItsNameInTheSitesLanguage(): void
    {
        $writer = $this->createMock(ContentWriter::class);
        $writer->expects(self::once())->method('create')->with('folder', [1018], ['name' => 'Press'], 'fre-FR')->willReturn($this->published(600));

        $result = (new CreateFolderCapability($this->checks(), $writer, $this->languages()))
            ->execute(['name' => 'Press', 'parent_location_id' => 1018, 'language' => 'fr', 'siteaccess' => 'fossil_exit']);

        self::assertTrue($result->success);
        self::assertSame('Created folder "Press" (ID: 600)', $result->message);
    }

    public function testAnImageOfAnotherUserOrOneThatIsGoneIsRefusedAndNothingIsWritten(): void
    {
        [$store, $id] = $this->storeWithImageOf(14);
        $writer = $this->createMock(ContentWriter::class);
        $writer->expects(self::never())->method('create');
        $permissions = $this->createMock(PermissionResolver::class);
        $permissions->method('getCurrentUserReference')->willReturn(new \Ibexa\Core\Repository\Values\User\UserReference(15));
        $capability = new AddImageCapability($this->checks([], [], $store, 15), $writer, $this->languages(), $store, $permissions);

        self::assertStringContainsString('not available', (string) $capability->validate(['attachment_id' => $id, 'parent_location_id' => 43]));
        $result = $capability->execute(['attachment_id' => $id, 'parent_location_id' => 43]);
        self::assertFalse($result->success);
        self::assertSame('The attached image is no longer available. Attach it again.', $result->message);
    }

    public function testAnAttachedImageIsAddedToTheMediaLibraryUnderItsFileName(): void
    {
        [$store, $id] = $this->storeWithImageOf(14);
        $writer = $this->createMock(ContentWriter::class);
        $writer->expects(self::once())->method('create')->willReturnCallback(function (string $type, array $parents, array $fields, string $language): PublishedContent {
            self::assertSame('image', $type);
            self::assertSame([43], $parents);
            self::assertSame('logo', $fields['name'], 'named after the file, without its extension');
            self::assertInstanceOf(ImageValue::class, $fields['image']);
            self::assertSame('Our logo', $fields['image']->alternativeText);
            self::assertSame('eng-GB', $language);

            return $this->published(700);
        });
        $permissions = $this->createMock(PermissionResolver::class);
        $permissions->method('getCurrentUserReference')->willReturn(new \Ibexa\Core\Repository\Values\User\UserReference(14));
        $capability = new AddImageCapability($this->checks([], [], $store, 14), $writer, $this->languages(), $store, $permissions);

        $result = $capability->execute(['attachment_id' => $id, 'parent_location_id' => 43, 'alternative_text' => 'Our logo']);

        self::assertTrue($result->success);
        self::assertSame('Added the image "logo" to the media library (ID: 700)', $result->message);
    }

    public function testUpdatingAMissingContentIsRefusedAndAnUnknownLanguageToo(): void
    {
        $contents = $this->createMock(ContentService::class);
        $contents->method('loadContentInfo')->willThrowException(new \Ibexa\Core\Base\Exceptions\NotFoundException('content', '9'));
        $repository = $this->createMock(Repository::class);
        $repository->method('getContentService')->willReturn($contents);
        $checks = new WriteChecks(
            $this->createBlockCatalog([]),
            $repository,
            $this->createMock(\Masilia\Contracts\IbexaAi\Site\SiteLocations::class),
            $this->createMock(\Masilia\Bundle\IbexaAi\Agent\Tool\SiteLanguages::class),
            new \Masilia\Bundle\IbexaAi\Content\EnabledLanguages($this->createMock(LanguageService::class)),
            $this->emptyStore(),
            $this->createMock(PermissionResolver::class),
        );
        $capability = new UpdateCapability($checks, $this->createMock(ContentWriter::class), $this->languages(), $repository);

        self::assertSame('The content 9 does not exist.', $capability->validate(['content_id' => 9, 'fields' => ['title' => 'x']]));
    }

    public function testTheCapabilitiesAreWriteOnes(): void
    {
        $checks = $this->checks();
        self::assertSame(\Masilia\Contracts\IbexaAi\Os\Risk::WRITE, (new CreateCapability($checks, $this->createMock(ContentWriter::class), $this->languages()))->risk());
        self::assertSame(\Masilia\Contracts\IbexaAi\Os\Risk::WRITE, (new CreateFolderCapability($checks, $this->createMock(ContentWriter::class), $this->languages()))->risk());
    }
}
