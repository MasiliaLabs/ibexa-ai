<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Tests\Agent\Worker;

use Ibexa\Contracts\Core\Repository\LanguageService;
use Ibexa\Contracts\Core\Repository\ContentTypeService;
use Ibexa\Contracts\Core\Repository\LocationService;
use Ibexa\Contracts\Core\Repository\Repository;
use Ibexa\Contracts\Core\Repository\Values\ContentType\ContentType;
use Ibexa\Core\Base\Exceptions\NotFoundException;
use Ibexa\Contracts\Core\Repository\Values\Content\Language;
use Ibexa\Core\Repository\Values\Content\Location;
use InvalidArgumentException;
use Doctrine\DBAL\DriverManager;
use Ibexa\Contracts\Core\Repository\PermissionResolver;
use Ibexa\Core\Repository\Values\User\UserReference;
use Masilia\Bundle\IbexaAi\Agent\Attachment\AttachmentStore;
use Masilia\Bundle\IbexaAi\Agent\Tool\SiteaccessLocationResolver;
use Masilia\Bundle\IbexaAi\Agent\Tool\SiteLanguages;
use Masilia\Bundle\IbexaAi\Agent\Worker\PlanBuilder;
use PHPUnit\Framework\TestCase;

/**
 * A plan must be refused before any approval when it points at something that does not exist
 * or at another site than its siteaccess. Locations: 1016 is the Fossil Exit site, 1018 its home,
 * 311 another site, 43 the media root (outside every site).
 */
final class PlanBuilderTargetsTest extends TestCase
{
    public function testAnUnknownContentTypeIsRefusedBeforeApproval(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Content type "licorne" does not exist');

        $this->builder()->build($this->plan(['content_type' => 'licorne']));
    }

    public function testAKnownContentTypeIsAccepted(): void
    {
        $plan = $this->builder()->build($this->plan(['content_type' => 'article']));

        self::assertSame('article', $plan->contentType);
    }

    public function testAMissingParentLocationIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Parent location 999999 does not exist');

        $this->builder()->build($this->plan(['parent_location_id' => 999999]));
    }

    public function testAParentInAnotherSiteIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Parent location 311 is under the site at location 311, but siteaccess "fossil_exit" belongs to the site at location 1016');

        $this->builder()->build($this->plan(['parent_location_id' => 311]));
    }

    public function testAParentInTheSameSiteOutsideTheHomeIsAccepted(): void
    {
        // Taxonomies sit under the site but not under the siteaccess tree root.
        $plan = $this->builder()->build($this->plan(['parent_location_id' => 1304]));

        self::assertSame(1304, $plan->parentLocationId);
    }

    public function testAParentOutsideEverySiteIsAccepted(): void
    {
        $plan = $this->builder()->build($this->plan(['parent_location_id' => 43]));

        self::assertSame(43, $plan->parentLocationId);
    }

    public function testALanguageIsAcceptedOnAnUpdate(): void
    {
        $plan = $this->builder()->build([
            'intent' => 'update_content',
            'content_id' => 1342,
            'language' => 'fre-FR',
            'fields' => ['title' => 'Titre'],
        ]);

        self::assertSame('fre-FR', $plan->language);
    }

    public function testAnUnknownLanguageIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Language "xx-XX" does not exist');

        $this->builder()->build([
            'intent' => 'update_content',
            'content_id' => 1342,
            'language' => 'xx-XX',
            'fields' => ['title' => 'Titre'],
        ]);
    }

    public function testAPlanWithoutLanguageIsWrittenInTheSiteaccessLanguageNotTheRepositoryDefault(): void
    {
        $plan = $this->builder(['fossil_exit_fr' => ['fre-FR', 'eng-GB']])->build($this->plan(['siteaccess' => 'fossil_exit_fr']));

        self::assertSame('fre-FR', $plan->language);
    }

    public function testAFrenchPlanOnTheEnglishSiteaccessIsMappedToTheSitesFrenchLanguage(): void
    {
        // fossil_exit is English, fossil_exit_fr French, on the same tree: French content gets fre-FR.
        $plan = $this->builder()->build($this->plan(['language' => 'fr']));

        self::assertSame('fre-FR', $plan->language);
    }

    public function testTheModelMayNameTheLanguageWithItsFullCode(): void
    {
        $plan = $this->builder()->build($this->plan(['language' => 'eng-GB']));

        self::assertSame('eng-GB', $plan->language);
    }

    public function testALanguageTheSiteIsNotServedInIsRefusedInsteadOfWritingItAsAnotherOne(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('has no "fr" language: it is served in eng-GB');

        $this->builder(['fossil_exit' => ['eng-GB']])->build($this->plan(['language' => 'fr']));
    }

    public function testALanguageTheRepositoryDoesNotHaveIsRefusedAsUnknown(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Language "de" does not exist');

        $this->builder()->build($this->plan(['language' => 'de']));
    }

    public function testAnUpdateWithoutLanguageKeepsTheLanguageOfTheContent(): void
    {
        $plan = $this->builder()->build(['intent' => 'update_content', 'content_id' => 1342, 'siteaccess' => 'fossil_exit_fr', 'fields' => ['title' => 'Titre']]);

        self::assertNull($plan->language);
    }

    private function storeWithImageOf(int $userId): array
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $connection->executeStatement('CREATE TABLE app_ai_attachment (
            id CHAR(36) PRIMARY KEY, user_id INT NOT NULL, name VARCHAR(160) NOT NULL, mime VARCHAR(40) NOT NULL, size INT NOT NULL,
            width INT NOT NULL, height INT NOT NULL, filename VARCHAR(80) NOT NULL, created_at DATETIME NOT NULL)');
        $store = new AttachmentStore($connection, sys_get_temp_dir() . '/ai-plan-test-' . bin2hex(random_bytes(4)));
        $path = tempnam(sys_get_temp_dir(), 'img') ?: '';
        imagepng(imagecreatetruecolor(20, 20), $path);

        return [$store, $store->store($path, 'logo.png', $userId)->id];
    }

    public function testAnImagePlanNeedsItsAttachmentAndAFolder(): void
    {
        try {
            $this->builder()->build(['intent' => 'add_image', 'parent_location_id' => 43]);
            self::fail('no attachment');
        } catch (InvalidArgumentException $e) {
            self::assertStringContainsString('requires attachment_id', $e->getMessage());
        }

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('requires parent_location_id');
        $this->builder()->build(['intent' => 'add_image', 'attachment_id' => '3f2b8c1e-5d6a-4e7f-9a10-2b3c4d5e6f70']);
    }

    public function testAnImageOfTheUserIsAccepted(): void
    {
        [$store, $id] = $this->storeWithImageOf(14);

        $plan = $this->builder([], $store, 14)->build(['intent' => 'add_image', 'attachment_id' => $id, 'parent_location_id' => 43, 'title' => 'Logo']);

        self::assertSame($id, $plan->attachmentId);
        self::assertSame(43, $plan->parentLocationId);
    }

    public function testAnImageOfAnotherUserOrOneThatIsGoneIsRefusedBeforeApproval(): void
    {
        [$store, $id] = $this->storeWithImageOf(15);

        try {
            $this->builder([], $store, 14)->build(['intent' => 'add_image', 'attachment_id' => $id, 'parent_location_id' => 43]);
            self::fail('another user');
        } catch (InvalidArgumentException $e) {
            self::assertStringContainsString('not available', $e->getMessage());
        }

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('not available');
        $this->builder([], $store, 15)->build(['intent' => 'add_image', 'attachment_id' => '00000000-0000-4000-8000-000000000000', 'parent_location_id' => 43]);
    }

    public function testAFolderPlanChecksItsParentToo(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Parent location 999999 does not exist');

        $this->builder()->build([
            'intent' => 'create_folder',
            'title' => 'Essai',
            'siteaccess' => 'fossil_exit',
            'parent_location_id' => 999999,
        ]);
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function plan(array $overrides): array
    {
        return $overrides + [
            'intent' => 'create_content',
            'content_type' => 'article',
            'title' => 'Essai',
            'siteaccess' => 'fossil_exit',
            'parent_location_id' => 1018,
            'fields' => ['title' => 'Essai'],
        ];
    }

    /**
     * @param array<string, list<string>> $languagesBySiteaccess Siteaccesses of one site and their languages; by default
     *                                                           fossil_exit (eng-GB), fossil_exit_fr (fre-FR), fossil_exit_ar (ara-MA)
     */
    private function builder(array $languagesBySiteaccess = [], ?AttachmentStore $attachments = null, int $userId = 14): PlanBuilder
    {
        $languagesBySiteaccess = $languagesBySiteaccess ?: ['fossil_exit' => ['eng-GB'], 'fossil_exit_fr' => ['fre-FR'], 'fossil_exit_ar' => ['ara-MA']];
        $paths = [
            1018 => '/1/2/1016/1018/',
            1304 => '/1/2/1016/1304/',
            311 => '/1/2/311/',
            43 => '/1/43/',
        ];

        $locations = $this->createMock(LocationService::class);
        $locations->method('loadLocation')->willReturnCallback(
            function (int $id) use ($paths): Location {
                if (!isset($paths[$id])) {
                    throw new NotFoundException('location', (string) $id);
                }

                return new Location(['id' => $id, 'pathString' => $paths[$id]]);
            },
        );

        $types = $this->createMock(ContentTypeService::class);
        $types->method('loadContentTypeByIdentifier')->willReturnCallback(
            function (string $identifier): ContentType {
                if ($identifier !== 'article') {
                    throw new NotFoundException('content type', $identifier);
                }

                return $this->createMock(ContentType::class);
            },
        );

        $languages = $this->createMock(LanguageService::class);
        $languages->method('loadLanguages')->willReturn([
            new Language(['languageCode' => 'eng-GB', 'enabled' => true]),
            new Language(['languageCode' => 'fre-FR', 'enabled' => true]),
            new Language(['languageCode' => 'ara-MA', 'enabled' => true]),
        ]);
        $languages->method('loadLanguage')->willReturnCallback(
            function (string $code): Language {
                if (!in_array($code, ['eng-GB', 'fre-FR', 'ara-MA'], true)) {
                    throw new NotFoundException('language', $code);
                }

                return new Language(['languageCode' => $code]);
            },
        );

        $repository = $this->createMock(Repository::class);
        $repository->method('getLocationService')->willReturn($locations);
        $repository->method('getContentTypeService')->willReturn($types);
        $repository->method('getContentLanguageService')->willReturn($languages);

        $resolver = $this->createMock(SiteaccessLocationResolver::class);
        $resolver->method('resolve')->willReturn(1018);

        $siteLanguages = $this->createMock(SiteLanguages::class);
        $siteLanguages->method('forSiteaccess')->willReturnCallback(
            static function (string $siteaccess) use ($languagesBySiteaccess): array {
                if (!isset($languagesBySiteaccess[$siteaccess])) {
                    return [];
                }
                // The siteaccess's own language first, then the ones of the siteaccesses of the same site.
                return array_values(array_unique(array_merge(...array_values([$siteaccess => $languagesBySiteaccess[$siteaccess]] + $languagesBySiteaccess))));
            },
        );

        $permissions = $this->createMock(PermissionResolver::class);
        $permissions->method('getCurrentUserReference')->willReturn(new UserReference($userId));

        return new PlanBuilder(repository: $repository, siteaccessResolver: $resolver, siteLanguages: $siteLanguages, attachments: $attachments, permissions: $attachments !== null ? $permissions : null);
    }
}
