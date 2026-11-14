<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Tests\Lib\Os;

use Ibexa\Contracts\Core\Repository\LocationService;
use Ibexa\Contracts\Core\Repository\Repository;
use Ibexa\Contracts\Core\Repository\URLAliasService;
use Ibexa\Contracts\Core\Repository\Values\Content\Location;
use Masilia\IbexaAi\Ibexa\Site\SiteUrls;
use Masilia\IbexaAi\Os\Capability\Url\AliasListCapability;
use PHPUnit\Framework\TestCase;

/** The link of a page is the front address; the admin one is given only when asked for. */
final class AliasListCapabilityTest extends TestCase
{
    private function capability(SiteUrls $urls): AliasListCapability
    {
        $location = $this->createMock(Location::class);
        $locations = $this->createMock(LocationService::class);
        $locations->method('loadLocation')->willReturn($location);
        $aliases = $this->createMock(URLAliasService::class);
        $aliases->method('listLocationAliases')->willReturn([]);
        $repository = $this->createMock(Repository::class);
        $repository->method('getLocationService')->willReturn($locations);
        $repository->method('getURLAliasService')->willReturn($aliases);

        return new AliasListCapability($repository, $urls);
    }

    public function testTheAdminAddressIsNotGivenUnlessAskedFor(): void
    {
        $urls = $this->createMock(SiteUrls::class);
        $urls->method('forLocation')->willReturn([['siteaccess' => 'shop', 'url' => 'https://shop.test/']]);
        $urls->expects(self::never())->method('adminUrl');

        $data = $this->capability($urls)->execute(['location_id' => 5])->data;

        self::assertSame('https://shop.test/', $data['urls'][0]['url']);
        self::assertArrayNotHasKey('admin_urls', $data);
    }

    public function testAskedForTheAdminAddressItIsGivenOncePerAdmin(): void
    {
        $urls = $this->createMock(SiteUrls::class);
        $urls->method('forLocation')->willReturn([['siteaccess' => 'shop', 'url' => 'https://shop.test/'], ['siteaccess' => 'shop_fr', 'url' => 'https://shop.test/fr']]);
        $urls->method('adminUrl')->willReturn(['siteaccess' => 'admin_shop', 'url' => 'https://admin.shop.test/view']);

        $data = $this->capability($urls)->execute(['location_id' => 5, 'admin' => true])->data;

        self::assertSame([['siteaccess' => 'admin_shop', 'url' => 'https://admin.shop.test/view']], $data['admin_urls']);
    }
}
