<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Tests\Lib\Ibexa\Site;

use Ibexa\AdminUi\Siteaccess\NonAdminSiteaccessResolver;
use Ibexa\Contracts\Core\Repository\Values\Content\ContentInfo;
use Ibexa\Contracts\Core\Repository\Values\Content\Location;
use Ibexa\Contracts\Core\SiteAccess\ConfigResolverInterface;
use Ibexa\Core\MVC\Symfony\SiteAccess;
use Ibexa\Core\MVC\Symfony\SiteAccess\SiteAccessServiceInterface;
use Masilia\IbexaAi\Ibexa\Site\SiteUrls;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

/**
 * The sites that show a page are the admin's own answer (its preview resolver); the addresses are the router's; which admin goes with
 * which site is read from the domain of the session cookie they share.
 */
final class SiteUrlsTest extends TestCase
{
    private const COOKIES = [
        'default' => '.main.test',
        'shop' => '.shop.test', 'shop_fr' => '.shop.test', 'admin_shop' => '.shop.test',
        'blog' => '.main.test', 'admin' => '.main.test',
    ];

    /** @param list<string> $shows The front siteaccesses the admin's resolver says show the location */
    private function urls(array $shows, string $current, ?RouterInterface $router = null): SiteUrls
    {
        $service = $this->createMock(SiteAccessServiceInterface::class);
        $service->method('getCurrent')->willReturn(new SiteAccess($current));
        $service->method('getAll')->willReturn(array_map(static fn (string $name): SiteAccess => new SiteAccess($name), ['shop', 'shop_fr', 'blog', 'admin', 'admin_shop']));
        $config = $this->createMock(ConfigResolverInterface::class);
        $config->method('getParameter')->willReturnCallback(static fn (string $name, ?string $namespace, ?string $siteaccess): array => ['cookie_domain' => self::COOKIES[$siteaccess]]);
        $resolver = $this->createMock(NonAdminSiteaccessResolver::class);
        $resolver->method('getSiteAccessesListForLocation')->willReturn(array_map(static fn (string $name): SiteAccess => new SiteAccess($name), $shows));
        $router ??= $this->createMock(RouterInterface::class);

        return new SiteUrls($router, $service, $config, $resolver, ['admin' => ['admin_group'], 'admin_shop' => ['admin_group']], 'admin_group');
    }

    private function location(): Location
    {
        $info = $this->createMock(ContentInfo::class);
        $info->method('getId')->willReturn(900);
        $location = $this->createMock(Location::class);
        $location->method('getId')->willReturn(55);
        $location->method('getContentInfo')->willReturn($info);

        return $location;
    }

    private function router(): RouterInterface
    {
        $router = $this->createMock(RouterInterface::class);
        $router->method('generate')->willReturnCallback(static fn (string $route, array $parameters, int $type): string => 'https://' . $parameters['siteaccess'] . '.example.org/' . $route);

        return $router;
    }

    public function testTheGlobalAdminGetsEverySiteThatShowsThePage(): void
    {
        $urls = $this->urls(['shop', 'shop_fr', 'blog'], 'admin', $this->router())->forLocation($this->location());

        self::assertSame(['shop', 'shop_fr', 'blog'], array_column($urls, 'siteaccess'));
        self::assertSame('https://shop.example.org/ibexa.url.alias', $urls[0]['url']);
    }

    public function testAnAdminDedicatedToASiteOnlyGetsTheSitesOfThatAdmin(): void
    {
        $urls = $this->urls(['shop', 'shop_fr', 'blog'], 'admin_shop', $this->router())->forLocation($this->location());

        self::assertSame(['shop', 'shop_fr'], array_column($urls, 'siteaccess'), 'the contributor of the shop does not get the blog');
    }

    public function testOutsideTheAdminEverySiteThatShowsThePageIsGiven(): void
    {
        self::assertSame(['blog'], array_column($this->urls(['blog'], 'blog', $this->router())->forLocation($this->location()), 'siteaccess'));
    }

    public function testAskedForOneSiteaccessItGivesThatOne(): void
    {
        $urls = $this->urls(['shop'], 'admin', $this->router())->forLocation($this->location(), 'shop_fr');

        self::assertSame(['shop_fr'], array_column($urls, 'siteaccess'));
    }

    public function testASiteaccessThatCannotLinkIsLeftOut(): void
    {
        $router = $this->createMock(RouterInterface::class);
        $router->method('generate')->willThrowException(new \RuntimeException('no route'));

        self::assertSame([], $this->urls(['shop'], 'admin', $router)->forLocation($this->location()));
    }

    public function testTheAdminAddressIsOnTheAdminTheUserIsIn(): void
    {
        $shop = $this->urls([], 'admin_shop', $this->router())->adminUrl($this->location(), 'blog');
        $global = $this->urls([], 'admin', $this->router())->adminUrl($this->location(), 'shop');

        self::assertSame('admin_shop', $shop['siteaccess'] ?? null);
        self::assertSame('https://admin_shop.example.org/ibexa.content.view', $shop['url'] ?? null);
        self::assertSame('admin', $global['siteaccess'] ?? null, 'the global admin stays the global admin, whatever the site of the page');
    }

    public function testOutsideAnAdminTheAdminOfTheSiteOfTheFrontIsUsed(): void
    {
        $urls = $this->urls([], 'blog', $this->router());

        self::assertSame('admin_shop', $urls->adminUrl($this->location(), 'shop')['siteaccess'] ?? null);
        self::assertSame('admin', $urls->adminUrl($this->location(), 'blog')['siteaccess'] ?? null);
    }

    public function testTheAdminAddressUsesTheContentAndLocationIds(): void
    {
        $router = $this->createMock(RouterInterface::class);
        $router->expects(self::once())->method('generate')->with('ibexa.content.view', ['contentId' => 900, 'locationId' => 55, 'siteaccess' => 'admin'], UrlGeneratorInterface::ABSOLUTE_URL)->willReturn('https://x');

        self::assertSame('https://x', $this->urls([], 'admin', $router)->adminUrl($this->location(), 'blog')['url'] ?? null);
    }
}
