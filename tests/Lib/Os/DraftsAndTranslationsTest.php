<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Tests\Lib\Os;

use Ibexa\Contracts\Core\Repository\ContentService;
use Ibexa\Contracts\Core\Repository\LanguageService;
use Ibexa\Contracts\Core\Repository\Repository;
use Ibexa\Contracts\Core\Repository\UserService;
use Ibexa\Contracts\Core\Repository\Values\Content\Content;
use Ibexa\Contracts\Core\Repository\Values\Content\Language;
use Ibexa\Contracts\Core\Repository\Values\Content\Location;
use Ibexa\Contracts\Core\Repository\Values\Content\VersionInfo;
use Ibexa\Contracts\Core\Repository\Values\Content\ContentInfo;
use Masilia\IbexaAi\Os\Capability\Content\CreateCapability;
use Masilia\IbexaAi\Os\Capability\Content\UpdateCapability;
use Masilia\IbexaAi\Os\Capability\Localization\TranslationAddCapability;
use Masilia\Contracts\IbexaAi\Content\ContentWriter;
use Masilia\Contracts\IbexaAi\Content\Languages;
use Masilia\Contracts\IbexaAi\Content\PublishedContent;
use Masilia\IbexaAi\Tests\Lib\Ibexa\Content\WriteFixtures;
use PHPUnit\Framework\TestCase;

/** What the editing form of the admin offers beyond "publish": a draft, a translation from another language, a user form of its own. */
final class DraftsAndTranslationsTest extends TestCase
{
    use WriteFixtures;

    private function languages(): Languages
    {
        return new class() implements Languages {
            public function resolve(string $requested): ?string
            {
                return in_array($requested, ['eng-GB', 'fre-FR'], true) ? $requested : null;
            }

            public function default(): string
            {
                return 'eng-GB';
            }
        };
    }

    private function draft(): PublishedContent
    {
        $version = $this->createMock(VersionInfo::class);
        $version->method('getVersionNo')->willReturn(3);
        $content = $this->createMock(Content::class);
        $content->method('getId')->willReturn(500);
        $content->method('getVersionInfo')->willReturn($version);

        return new PublishedContent($content, null);
    }

    private function article(): array
    {
        return ['article' => ['name' => 'Article', 'fields' => ['title' => 'ibexa_string']]];
    }

    public function testADraftIsSavedAndSaysHowToPublishIt(): void
    {
        $writer = $this->createMock(ContentWriter::class);
        $writer->expects(self::once())->method('create')->with('article', [1018], ['title' => 'Later'], 'eng-GB', false)->willReturn($this->draft());
        $capability = new CreateCapability($this->checks($this->article()), $writer, $this->languages());

        $result = $capability->execute(['content_type' => 'article', 'parent_location_id' => 1018, 'fields' => ['title' => 'Later'], 'publish' => false, 'siteaccess' => 'fossil_exit']);

        self::assertTrue($result->success);
        self::assertStringContainsString('Saved a draft of article (ID: 500): publish it with content.publish', $result->message);
        self::assertSame(3, $result->data['version_no']);
        self::assertNull($result->data['location_id']);
        self::assertStringContainsString('Saved as a draft', implode(' ', $capability->preview(['content_type' => 'article', 'parent_location_id' => 1018, 'fields' => ['title' => 'x'], 'publish' => false])->notes));
    }

    public function testAUserIsNotCreatedAsAContent(): void
    {
        $capability = new CreateCapability($this->checks(['user' => ['name' => 'User', 'fields' => ['first_name' => 'ibexa_string']]]), $this->createMock(ContentWriter::class), $this->languages());

        self::assertStringContainsString('access.user.create', (string) $capability->validate(['content_type' => 'user', 'parent_location_id' => 1018, 'fields' => ['first_name' => 'Ada']]));
    }

    public function testAnUpdateRefusesAUserAndPointsToTheUserCapability(): void
    {
        $contents = $this->createMock(ContentService::class);
        $contents->method('loadContentInfo')->willReturn(new ContentInfo(['id' => 5, 'name' => 'Ada', 'mainLanguageCode' => 'eng-GB']));
        $contents->method('loadContent')->willReturn($this->createMock(Content::class));
        $users = $this->createMock(UserService::class);
        $users->method('isUser')->willReturn(true);
        $repository = $this->createMock(Repository::class);
        $repository->method('getContentService')->willReturn($contents);
        $repository->method('getUserService')->willReturn($users);
        $checks = new \Masilia\IbexaAi\Ibexa\Content\Write\WriteChecks($this->createBlockCatalog([]), $repository, $this->createMock(\Masilia\Contracts\IbexaAi\Site\SiteLocations::class), $this->createMock(\Masilia\IbexaAi\Ibexa\Site\SiteLanguages::class), new \Masilia\IbexaAi\Ibexa\Content\EnabledLanguages($this->createMock(LanguageService::class)), $this->emptyStore(), $this->createMock(\Ibexa\Contracts\Core\Repository\PermissionResolver::class), $this->config());

        self::assertStringContainsString('use access.user.update', (string) (new UpdateCapability($checks, $this->createMock(ContentWriter::class), $this->languages(), $repository))->validate(['content_id' => 5, 'fields' => ['first_name' => 'x']]));
    }

    private function updateWithDrafts(array $drafts): UpdateCapability
    {
        $info = new ContentInfo(['id' => 7, 'name' => 'Home', 'mainLanguageCode' => 'eng-GB', 'currentVersionNo' => 2]);
        $contents = $this->createMock(ContentService::class);
        $contents->method('loadContentInfo')->willReturn($info);
        $contents->method('loadVersions')->willReturn($drafts);
        $repository = $this->createMock(Repository::class);
        $repository->method('getContentService')->willReturn($contents);

        return new UpdateCapability($this->checks(), $this->createMock(ContentWriter::class), $this->languages(), $repository);
    }

    private function draftVersion(int $no, string $language): VersionInfo
    {
        $version = $this->createMock(VersionInfo::class);
        $version->method('isDraft')->willReturn(true);
        $version->method('getVersionNo')->willReturn($no);
        $version->method('getInitialLanguage')->willReturn(new Language(['languageCode' => $language]));

        return $version;
    }

    public function testTheAdminsDraftConflictIsSaidOnTheCardOfAnUpdate(): void
    {
        // Admin: ContentDraftHasConflict, a draft newer than the published version, started in the same language.
        $notes = fn (UpdateCapability $capability): string => implode(' ', $capability->preview(['content_id' => 7, 'fields' => ['title' => 'x']])->notes);

        self::assertStringContainsString('Another draft of this content exists', $notes($this->updateWithDrafts([$this->draftVersion(3, 'eng-GB')])));
        self::assertStringNotContainsString('Another draft', $notes($this->updateWithDrafts([$this->draftVersion(3, 'fre-FR')])), 'a draft in another language does not conflict');
        self::assertStringNotContainsString('Another draft', $notes($this->updateWithDrafts([$this->draftVersion(1, 'eng-GB')])), 'an older draft does not either');
    }

    public function testATranslationCanStartFromAnotherLanguageOfTheContent(): void
    {
        $version = $this->createMock(VersionInfo::class);
        $version->method('__get')->willReturn(['eng-GB']);
        $content = $this->createMock(Content::class);
        $content->method('getVersionInfo')->willReturn($version);
        $contents = $this->createMock(ContentService::class);
        $contents->method('loadContent')->willReturn($content);
        $languageService = $this->createMock(\Ibexa\Contracts\Core\Repository\LanguageService::class);
        $languageService->method('loadLanguage')->willReturn(new Language(['languageCode' => 'fre-FR', 'enabled' => true]));
        $repository = $this->createMock(Repository::class);
        $repository->method('getContentService')->willReturn($contents);
        $repository->method('getContentLanguageService')->willReturn($languageService);
        $writer = $this->createMock(ContentWriter::class);
        $writer->expects(self::once())->method('update')->with(7, [], 'fre-FR', true, 'eng-GB')->willReturn($content);
        $capability = new TranslationAddCapability($repository, $writer);

        self::assertNull($capability->validate(['content_id' => 7, 'language_code' => 'fre-FR', 'base_language_code' => 'eng-GB']), 'no field to give is fine when it starts from a language');
        self::assertStringContainsString('has no "ara-MA" translation to start from', (string) $capability->validate(['content_id' => 7, 'language_code' => 'fre-FR', 'base_language_code' => 'ara-MA']));
        self::assertStringContainsString('give the values', (string) $capability->validate(['content_id' => 7, 'language_code' => 'fre-FR']));
        $capability->execute(['content_id' => 7, 'language_code' => 'fre-FR', 'base_language_code' => 'eng-GB']);
    }
}
