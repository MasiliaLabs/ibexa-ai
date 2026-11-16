<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Tests\Lib\Ibexa\Content;

use Doctrine\DBAL\DriverManager;
use Ibexa\Contracts\Core\Repository\ContentTypeService;
use Ibexa\Contracts\Core\Repository\LanguageService;
use Ibexa\Contracts\Core\Repository\LocationService;
use Ibexa\Contracts\Core\Repository\PermissionResolver;
use Ibexa\Contracts\Core\Repository\Repository;
use Ibexa\Core\Base\Exceptions\NotFoundException;
use Ibexa\Contracts\Core\Repository\Values\Content\Language;
use Ibexa\Core\Repository\Values\Content\Location;
use Ibexa\Core\Repository\Values\User\UserReference;
use Masilia\IbexaAi\Chat\Attachment\AttachmentStore;
use Masilia\IbexaAi\Ibexa\Site\SiteLanguages;
use Masilia\IbexaAi\Ibexa\Content\EnabledLanguages;
use Masilia\IbexaAi\Ibexa\Content\Write\WriteChecks;
use Masilia\Contracts\IbexaAi\Site\SiteLocations;
use Masilia\IbexaAi\Tests\Lib\Ibexa\Schema\BlockCatalogFactoryTrait;
use PHPUnit\Framework\TestCase;

/**
 * What must be true before the assistant writes: the content type exists and its values fit it, the parent is in the
 * site of the siteaccess, the language is one the site is served in, the image is the user's own. Locations: 1018 is the
 * Acme Shop site's home, 1304 another page of it, 311 another site, 43 the media root (outside every site).
 */
final class WriteChecksTest extends TestCase
{
    use WriteFixtures;

    public function testAnUnknownContentTypeIsRefusedBeforeApproval(): void
    {
        self::assertStringContainsString('Content type "licorne" does not exist', (string) $this->checks()->contentType('licorne', ['title' => 'x']));
    }

    public function testAKnownContentTypeWithItsRequiredFieldsIsAccepted(): void
    {
        $types = ['article' => ['name' => 'Article', 'fields' => ['title' => ['type' => 'ibexa_string', 'required' => true], 'body' => 'ibexa_text']]];

        self::assertNull($this->checks($types)->contentType('article', ['title' => 'Hello']));
    }

    public function testAnOptionalFieldMayBeEmpty(): void
    {
        $types = ['article' => ['name' => 'Article', 'fields' => ['title' => 'ibexa_string', 'body' => 'ibexa_text']]];

        self::assertNull($this->checks($types)->contentType('article', ['title' => 'Hello', 'body' => '']));
    }

    public function testAMatrixRowWithAColumnThatDoesNotExistIsRefused(): void
    {
        $types = ['info_cards' => ['name' => 'Info Cards', 'fields' => ['cards' => [
            'type' => 'ibexa_matrix',
            'settings' => ['columns' => [['identifier' => 'icon', 'name' => 'Icon'], ['identifier' => 'title', 'name' => 'Title']]],
        ]]]];
        $checks = $this->checks($types);

        self::assertNull($checks->contentType('info_cards', ['cards' => [['icon' => 'star', 'title' => 'Hello'], ['icon' => 'check', 'title' => 'Second']]]));
        self::assertStringContainsString('matrix field "cards" row 0 has unknown column "guess"', (string) $checks->contentType('info_cards', ['cards' => [['guess' => 'x']]]));
    }

    public function testAParentThatDoesNotExistIsRefused(): void
    {
        self::assertStringContainsString('Parent location 9999 does not exist', (string) $this->checks()->parent(9999, 'acme'));
    }

    public function testAParentInAnotherSiteIsRefused(): void
    {
        self::assertStringContainsString('they are in different sites', (string) $this->checks()->parent(311, 'acme'));
    }

    public function testAParentUnderTheSiteOrOutsideEverySiteIsAccepted(): void
    {
        $checks = $this->checks();

        self::assertNull($checks->parent(1304, 'acme'), 'another page of the same site');
        self::assertNull($checks->parent(43, 'acme'), 'the media library belongs to no site');
        self::assertNull($checks->parent(1018, null), 'without a siteaccess there is nothing to compare with');
    }

    public function testWithoutALanguageTheSitesOwnLanguageIsUsed(): void
    {
        self::assertSame(['eng-GB', null], $this->checks()->language(null, 'acme'));
        self::assertSame([null, null], $this->checks()->language(null, null), 'no site and no language: the repository default');
    }

    public function testAFrenchCodeOnTheSiteIsMappedToTheSitesFrenchLanguage(): void
    {
        self::assertSame(['fre-FR', null], $this->checks()->language('fr', 'acme'));
        self::assertSame(['fre-FR', null], $this->checks()->language('fre-FR', 'acme'));
    }

    public function testAnUnknownLanguageIsRefused(): void
    {
        [$language, $problem] = $this->checks()->language('xx', 'acme');

        self::assertNull($language);
        self::assertStringContainsString('Language "xx" does not exist', (string) $problem);
    }

    public function testALanguageTheSiteIsNotServedInIsRefusedInsteadOfWritingItAsAnotherOne(): void
    {
        [$language, $problem] = $this->checks([], ['acme' => ['eng-GB']])->language('fre-FR', 'acme');

        self::assertNull($language);
        self::assertStringContainsString('The site of siteaccess "acme" has no "fre-FR" language', (string) $problem);
    }

    public function testAnImageOfAnotherUserOrOneThatIsGoneIsRefused(): void
    {
        [$store, $id] = $this->storeWithImageOf(14);

        self::assertNull($this->checks([], [], $store, 14)->attachment($id));
        self::assertStringContainsString('not available', (string) $this->checks([], [], $store, 15)->attachment($id));
        self::assertStringContainsString('not available', (string) $this->checks([], [], $store, 14)->attachment('00000000-0000-0000-0000-000000000000'));
    }

    public function testAContentThatDoesNotExistIsRefused(): void
    {
        // A content that is not there is answered by the repository with a not-found error.
        $contents = $this->createMock(\Ibexa\Contracts\Core\Repository\ContentService::class);
        $contents->method('loadContentInfo')->willThrowException(new NotFoundException('content', '9'));
        $repository = $this->createMock(Repository::class);
        $repository->method('getContentService')->willReturn($contents);

        $checks = new WriteChecks(
            $this->createBlockCatalog([]),
            $repository,
            $this->createMock(SiteLocations::class),
            $this->createMock(SiteLanguages::class),
            new EnabledLanguages($this->createMock(LanguageService::class)),
            $this->emptyStore(),
            $this->createMock(PermissionResolver::class),
            $this->config(),
        );

        self::assertSame('The content 9 does not exist.', $checks->content(9));
    }

    public function testUsersAreNotCreatedNorChangedAsContentsBecauseTheAdminHasAUserFormForThem(): void
    {
        $checks = $this->checks([]);

        self::assertStringContainsString('access.user.create', (string) $checks->notAUserType('user'));
        self::assertNull($checks->notAUserType('article'));
    }

    public function testAContentThatIsAUserIsSentToTheUserCapability(): void
    {
        $contents = $this->createMock(\Ibexa\Contracts\Core\Repository\ContentService::class);
        $contents->method('loadContent')->willReturnCallback(fn (int $id) => $id === 5 ? $this->createMock(\Ibexa\Contracts\Core\Repository\Values\Content\Content::class) : throw new NotFoundException('content', (string) $id));
        $users = $this->createMock(\Ibexa\Contracts\Core\Repository\UserService::class);
        $users->method('isUser')->willReturn(true);
        $repository = $this->createMock(Repository::class);
        $repository->method('getContentService')->willReturn($contents);
        $repository->method('getUserService')->willReturn($users);
        $checks = new WriteChecks($this->createBlockCatalog([]), $repository, $this->createMock(SiteLocations::class), $this->createMock(SiteLanguages::class), new EnabledLanguages($this->createMock(LanguageService::class)), $this->emptyStore(), $this->createMock(PermissionResolver::class), $this->config());

        self::assertSame('Content 5 is a user: use access.user.update (the admin has a user form for it), not a content change.', $checks->notAUser(5, 'access.user.update'));
        self::assertNull($checks->notAUser(6, 'access.user.update'), 'a content that is not there is for the other checks to say');
    }

    /** @return array{WriteChecks, \Ibexa\Contracts\Core\Repository\ContentService&\PHPUnit\Framework\MockObject\MockObject} */
    private function checksFor(bool $allowed): WriteChecks
    {
        $contents = $this->createMock(\Ibexa\Contracts\Core\Repository\ContentService::class);
        $contents->method('newContentCreateStruct')->willReturn(new \Ibexa\Core\Repository\Values\Content\ContentCreateStruct(['mainLanguageCode' => 'eng-GB']));
        $contents->method('loadContentInfo')->willReturn($this->createMock(\Ibexa\Contracts\Core\Repository\Values\Content\ContentInfo::class));
        $types = $this->createMock(\Ibexa\Contracts\Core\Repository\ContentTypeService::class);
        $locations = $this->createMock(\Ibexa\Contracts\Core\Repository\LocationService::class);
        $locations->method('newLocationCreateStruct')->willReturn(new \Ibexa\Contracts\Core\Repository\Values\Content\LocationCreateStruct(['parentLocationId' => 2]));
        $languages = $this->createMock(LanguageService::class);
        $languages->method('getDefaultLanguageCode')->willReturn('eng-GB');
        $repository = $this->createMock(Repository::class);
        $repository->method('getContentService')->willReturn($contents);
        $repository->method('getContentTypeService')->willReturn($types);
        $repository->method('getLocationService')->willReturn($locations);
        $repository->method('getContentLanguageService')->willReturn($languages);
        $permissions = $this->createMock(PermissionResolver::class);
        $permissions->method('canUser')->willReturn($allowed);

        return new WriteChecks($this->createBlockCatalog([]), $repository, $this->createMock(SiteLocations::class), $this->createMock(SiteLanguages::class), new EnabledLanguages($languages), $this->emptyStore(), $permissions, $this->config());
    }

    public function testACreationTheRoleDoesNotAllowIsSaidBeforeTheCard(): void
    {
        self::assertNull($this->checksFor(true)->mayCreate('folder', 2));
        self::assertStringContainsString('does not let you create a "folder" under location 2', (string) $this->checksFor(false)->mayCreate('folder', 2));
    }

    public function testAnEditTheRoleDoesNotAllowIsSaidBeforeTheCard(): void
    {
        self::assertNull($this->checksFor(true)->mayEdit(9));
        self::assertStringContainsString('does not let you edit the content 9', (string) $this->checksFor(false)->mayEdit(9));
    }

    public function testATrashTheRoleDoesNotAllowIsSaidBeforeTheCard(): void
    {
        self::assertNull($this->checksFor(true)->mayTrash(9));
        self::assertStringContainsString('does not let you move the content 9 to the trash', (string) $this->checksFor(false)->mayTrash(9));
    }
}
