<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Tests\Bundle\Content;

use Doctrine\DBAL\DriverManager;
use Ibexa\Contracts\Core\Repository\ContentService;
use Ibexa\Contracts\Core\Repository\LanguageService;
use Ibexa\Contracts\Core\Repository\LocationService;
use Ibexa\Contracts\Core\Repository\PermissionResolver;
use Ibexa\Contracts\Core\Repository\Repository;
use Ibexa\Contracts\Core\Repository\Values\Content\Language;
use Ibexa\Core\Base\Exceptions\NotFoundException;
use Ibexa\Core\Repository\Values\Content\Location;
use Ibexa\Core\Repository\Values\User\UserReference;
use Masilia\Bundle\IbexaAi\Agent\Attachment\AttachmentStore;
use Masilia\Bundle\IbexaAi\Agent\Tool\SiteLanguages;
use Masilia\Bundle\IbexaAi\Content\EnabledLanguages;
use Masilia\Bundle\IbexaAi\Content\Write\WriteChecks;
use Masilia\Contracts\IbexaAi\Site\SiteLocations;
use Masilia\IbexaAi\Tests\Agent\Block\BlockCatalogFactoryTrait;

/**
 * What the write tests are built on: the checks as they are in a Fossil Exit installation, and a store with an image of a user.
 * Locations: 1018 is the Fossil Exit site's home, 1304 another page of it, 311 another site, 43 the media root.
 *
 * @internal test-only
 */
trait WriteFixtures
{
    use BlockCatalogFactoryTrait;

    /** @param array<string, list<string>> $languagesBySiteaccess */
    private function checks(array $types = [], array $languagesBySiteaccess = [], ?AttachmentStore $attachments = null, int $userId = 14): WriteChecks
    {
        $languagesBySiteaccess = $languagesBySiteaccess ?: ['fossil_exit' => ['eng-GB'], 'fossil_exit_fr' => ['fre-FR'], 'fossil_exit_ar' => ['ara-MA']];
        $paths = [1018 => '/1/2/1016/1018/', 1304 => '/1/2/1016/1304/', 311 => '/1/2/311/', 43 => '/1/43/'];

        $locations = $this->createMock(LocationService::class);
        $locations->method('loadLocation')->willReturnCallback(static function (int $id) use ($paths): Location {
            if (!isset($paths[$id])) {
                throw new NotFoundException('location', (string) $id);
            }

            return new Location(['id' => $id, 'pathString' => $paths[$id]]);
        });

        $languageService = $this->createMock(LanguageService::class);
        $languageService->method('loadLanguages')->willReturn([
            new Language(['languageCode' => 'eng-GB', 'enabled' => true]),
            new Language(['languageCode' => 'fre-FR', 'enabled' => true]),
            new Language(['languageCode' => 'ara-MA', 'enabled' => true]),
        ]);
        $languageService->method('getDefaultLanguageCode')->willReturn('eng-GB');

        $repository = $this->createMock(Repository::class);
        $repository->method('getLocationService')->willReturn($locations);

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

        $sites = $this->createMock(SiteLocations::class);
        $sites->method('rootLocationId')->willReturn(1018);

        $permissions = $this->createMock(PermissionResolver::class);
        $permissions->method('getCurrentUserReference')->willReturn(new UserReference($userId));

        return new WriteChecks(
            $this->createBlockCatalog($types),
            $repository,
            $sites,
            $siteLanguages,
            new EnabledLanguages($languageService),
            $attachments ?? $this->emptyStore(),
            $permissions,
        );
    }

    private function emptyStore(): AttachmentStore
    {
        return $this->storeWithImageOf(999)[0];
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
}
