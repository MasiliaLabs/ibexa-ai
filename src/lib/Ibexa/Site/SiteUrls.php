<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Ibexa\Site;

use Ibexa\Contracts\Core\Repository\Values\Content\Location;
use Ibexa\Contracts\Core\SiteAccess\ConfigResolverInterface;
use Ibexa\Core\MVC\Symfony\Routing\UrlAliasRouter;
use Ibexa\Core\MVC\Symfony\SiteAccess\SiteAccessServiceInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Throwable;

/**
 * The address a visitor uses for a location, the way Ibexa builds it: the router of the URL aliases, asked for a siteaccess. It
 * takes the host of the siteaccess, its language or path prefix, and leaves out the path of the site's root location, which the
 * system alias of the location (a path from the very root of the tree) does not.
 */
readonly class SiteUrls
{
    /**
     * @param array<string, list<string>> $groups The siteaccess groups of each siteaccess
     */
    public function __construct(
        private RouterInterface $router,
        private SiteAccessServiceInterface $siteAccesses,
        private ConfigResolverInterface $config,
        #[Autowire('%ibexa.site_access.groups_by_site_access%')]
        private array $groups,
        #[Autowire('%admin_group_name%')]
        private string $adminGroup,
    ) {
    }

    /**
     * The URL of the location on each front siteaccess whose site contains it (or on the one asked for).
     *
     * @return list<array{siteaccess: string, url: string}>
     */
    public function forLocation(Location $location, ?string $siteaccess = null): array
    {
        $urls = [];
        foreach ($this->siteAccesses->getAll() as $candidate) {
            $name = $candidate->name;
            if ($siteaccess !== null && $siteaccess !== $name) {
                continue;
            }
            if ($siteaccess === null && (in_array($this->adminGroup, $this->groups[$name] ?? [], true) || !$this->servesLocation($name, $location))) {
                continue;
            }
            try {
                $urls[] = ['siteaccess' => $name, 'url' => $this->router->generate(
                    UrlAliasRouter::URL_ALIAS_ROUTE_NAME,
                    ['locationId' => $location->getId(), 'siteaccess' => $name],
                    UrlGeneratorInterface::ABSOLUTE_URL,
                )];
            } catch (Throwable) {
                // A siteaccess that cannot link to it is left out.
            }
        }

        return $urls;
    }

    /** The location is the root of the siteaccess's site, or somewhere under it. */
    private function servesLocation(string $siteaccess, Location $location): bool
    {
        try {
            $root = (int) $this->config->getParameter('content.tree_root.location_id', null, $siteaccess);
        } catch (Throwable) {
            return false;
        }

        return $root > 0 && in_array((string) $root, explode('/', trim($location->getPathString(), '/')), true);
    }
}
