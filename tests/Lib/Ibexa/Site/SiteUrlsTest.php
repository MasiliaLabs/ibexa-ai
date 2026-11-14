<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Tests\Lib\Ibexa\Site;

use Ibexa\Contracts\Core\Repository\Values\Content\Location;
use Ibexa\Contracts\Core\SiteAccess\ConfigResolverInterface;
use Ibexa\Core\MVC\Symfony\SiteAccess;
use Ibexa\Core\MVC\Symfony\SiteAccess\SiteAccessServiceInterface;
use Masilia\IbexaAi\Ibexa\Site\SiteUrls;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

/** The link to a page is the router's, asked for a siteaccess: its host and prefix, not the path of the alias from the root of the tree. */
final class SiteUrlsTest extends TestCase
{
    private function urls(RouterInterface $router): SiteUrls
    {
        $service = $this->createMock(SiteAccessServiceInterface::class);
        $service->method('getAll')->willReturn([new SiteAccess('shop'), new SiteAccess('shop_fr'), new SiteAccess('other'), new SiteAccess('admin')]);
        $config = $this->createMock(ConfigResolverInterface::class);
        $config->method('getParameter')->willReturnCallback(static fn (string $name, ?string $namespace, ?string $siteaccess): int => match ($siteaccess) {
            'shop', 'shop_fr', 'admin' => 10,
            default => 99,
        });

        return new SiteUrls($router, $service, $config, ['shop' => ['front'], 'shop_fr' => ['front'], 'other' => ['front'], 'admin' => ['admin_group']], 'admin_group');
    }

    private function location(): Location
    {
        $location = $this->createMock(Location::class);
        $location->method('getId')->willReturn(55);
        $location->method('getPathString')->willReturn('/1/2/10/55/');

        return $location;
    }

    public function testEachFrontSiteOfTheLocationGivesItsAbsoluteAddressAndTheAdminAndOtherSitesAreLeftOut(): void
    {
        $router = $this->createMock(RouterInterface::class);
        $router->method('generate')->willReturnCallback(static function (string $route, array $parameters, int $type): string {
            self::assertSame('ibexa.url.alias', $route);
            self::assertSame(55, $parameters['locationId']);
            self::assertSame(UrlGeneratorInterface::ABSOLUTE_URL, $type);

            return 'https://' . $parameters['siteaccess'] . '.example.org/page';
        });

        self::assertSame(
            [['siteaccess' => 'shop', 'url' => 'https://shop.example.org/page'], ['siteaccess' => 'shop_fr', 'url' => 'https://shop_fr.example.org/page']],
            $this->urls($router)->forLocation($this->location()),
        );
    }

    public function testAskedForOneSiteaccessItGivesThatOne(): void
    {
        $router = $this->createMock(RouterInterface::class);
        $router->method('generate')->willReturn('https://shop.example.org/fr/page');

        self::assertSame([['siteaccess' => 'shop_fr', 'url' => 'https://shop.example.org/fr/page']], $this->urls($router)->forLocation($this->location(), 'shop_fr'));
    }

    public function testASiteaccessThatCannotLinkIsLeftOut(): void
    {
        $router = $this->createMock(RouterInterface::class);
        $router->method('generate')->willThrowException(new \RuntimeException('no route'));

        self::assertSame([], $this->urls($router)->forLocation($this->location()));
    }
}
