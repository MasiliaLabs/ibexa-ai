<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Tests\Bundle\Content;

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
use Masilia\Bundle\IbexaAi\Chat\Attachment\AttachmentStore;
use Masilia\Bundle\IbexaAi\Agent\Tool\SiteLanguages;
use Masilia\Bundle\IbexaAi\Content\EnabledLanguages;
use Masilia\Bundle\IbexaAi\Content\Write\WriteChecks;
use Masilia\Contracts\IbexaAi\Site\SiteLocations;
use Masilia\IbexaAi\Tests\Agent\Block\BlockCatalogFactoryTrait;
use PHPUnit\Framework\TestCase;

/**
 * What must be true before the assistant writes: the content type exists and its values fit it, the parent is in the
 * site of the siteaccess, the language is one the site is served in, the image is the user's own. Locations: 1018 is the
 * Fossil Exit site's home, 1304 another page of it, 311 another site, 43 the media root (outside every site).
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

    public function testARequiredFieldThatIsEmptyOrMissingIsRefused(): void
    {
        $types = ['article' => ['name' => 'Article', 'fields' => ['title' => ['type' => 'ibexa_string', 'required' => true]]]];
        $checks = $this->checks($types);

        self::assertStringContainsString('required field "title" is empty', (string) $checks->contentType('article', ['title' => '   ']));
        self::assertStringContainsString('required field "title" is empty', (string) $checks->contentType('article', ['title' => null]));
        self::assertStringContainsString('required field "title" is empty', (string) $checks->contentType('article', []));
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

    public function testARequiredMatrixNeedsAtLeastOneNonEmptyRow(): void
    {
        $types = ['info_cards' => ['name' => 'Info Cards', 'fields' => ['cards' => [
            'type' => 'ibexa_matrix', 'required' => true,
            'settings' => ['columns' => [['identifier' => 'title', 'name' => 'Title']]],
        ]]]];
        $checks = $this->checks($types);

        self::assertStringContainsString('required field "cards" is empty', (string) $checks->contentType('info_cards', ['cards' => []]));
        self::assertStringContainsString('required field "cards" is empty', (string) $checks->contentType('info_cards', ['cards' => [['title' => '']]]));
        self::assertNull($checks->contentType('info_cards', ['cards' => [['title' => 'Hello']]]));
    }

    public function testAParentThatDoesNotExistIsRefused(): void
    {
        self::assertStringContainsString('Parent location 9999 does not exist', (string) $this->checks()->parent(9999, 'fossil_exit'));
    }

    public function testAParentInAnotherSiteIsRefused(): void
    {
        self::assertStringContainsString('they are in different sites', (string) $this->checks()->parent(311, 'fossil_exit'));
    }

    public function testAParentUnderTheSiteOrOutsideEverySiteIsAccepted(): void
    {
        $checks = $this->checks();

        self::assertNull($checks->parent(1304, 'fossil_exit'), 'another page of the same site');
        self::assertNull($checks->parent(43, 'fossil_exit'), 'the media library belongs to no site');
        self::assertNull($checks->parent(1018, null), 'without a siteaccess there is nothing to compare with');
    }

    public function testWithoutALanguageTheSitesOwnLanguageIsUsed(): void
    {
        self::assertSame(['eng-GB', null], $this->checks()->language(null, 'fossil_exit'));
        self::assertSame([null, null], $this->checks()->language(null, null), 'no site and no language: the repository default');
    }

    public function testAFrenchCodeOnTheSiteIsMappedToTheSitesFrenchLanguage(): void
    {
        self::assertSame(['fre-FR', null], $this->checks()->language('fr', 'fossil_exit'));
        self::assertSame(['fre-FR', null], $this->checks()->language('fre-FR', 'fossil_exit'));
    }

    public function testAnUnknownLanguageIsRefused(): void
    {
        [$language, $problem] = $this->checks()->language('xx', 'fossil_exit');

        self::assertNull($language);
        self::assertStringContainsString('Language "xx" does not exist', (string) $problem);
    }

    public function testALanguageTheSiteIsNotServedInIsRefusedInsteadOfWritingItAsAnotherOne(): void
    {
        [$language, $problem] = $this->checks([], ['fossil_exit' => ['eng-GB']])->language('fre-FR', 'fossil_exit');

        self::assertNull($language);
        self::assertStringContainsString('The site of siteaccess "fossil_exit" has no "fre-FR" language', (string) $problem);
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
        );

        self::assertSame('The content 9 does not exist.', $checks->content(9));
    }
}
