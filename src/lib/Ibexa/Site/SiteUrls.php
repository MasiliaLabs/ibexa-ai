<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Ibexa\Site;

use Ibexa\AdminUi\Siteaccess\NonAdminSiteaccessResolver;
use Ibexa\Contracts\Core\Repository\Values\Content\Location;
use Ibexa\Contracts\Core\SiteAccess\ConfigResolverInterface;
use Ibexa\Core\MVC\Symfony\Routing\UrlAliasRouter;
use Ibexa\Core\MVC\Symfony\SiteAccess\SiteAccessServiceInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Throwable;

/**
 * The addresses of a location, the way Ibexa builds them.
 *
 * Which sites show it is the admin's own answer for its preview (the siteaccess resolver: the root of the site is on the path of the
 * location, same repository, language served), without the admin siteaccesses. The address is the router's, asked for a siteaccess:
 * its host, its language or path prefix, and without the path of the site's root, which the system alias lacks.
 *
 * Which admin goes with which site is read from the configuration: a siteaccess and the admin dedicated to it share the domain of
 * their session cookie (the contributor logs in once, on the admin of the site). A user in such an admin only gets the sites of that
 * domain; in the global admin, every site.
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
        private NonAdminSiteaccessResolver $sites,
        #[Autowire('%ibexa.site_access.groups_by_site_access%')]
        private array $groups,
        #[Autowire('%admin_group_name%')]
        private string $adminGroup,
    ) {
    }

    /**
     * The address on each front siteaccess that shows the location (or on the one asked for).
     *
     * @return list<array{siteaccess: string, url: string}>
     */
    public function forLocation(Location $location, ?string $siteaccess = null, ?string $language = null): array
    {
        if ($siteaccess !== null) {
            $names = [$siteaccess];
        } else {
            $names = array_values(array_map(static fn ($candidate): string => $candidate->name, $this->sites->getSiteAccessesListForLocation($location, null, $language)));
            $names = $this->ofTheCurrentAdmin($names);
        }

        $urls = [];
        foreach ($names as $name) {
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

    /**
     * The address of the location in the admin that goes with a front siteaccess: the admin dedicated to its site, else the one the
     * user is in, else any admin.
     *
     * @return array{siteaccess: string, url: string}|null
     */
    public function adminUrl(Location $location, ?string $front = null): ?array
    {
        $admin = $this->adminFor($front);
        if ($admin === null) {
            return null;
        }
        try {
            return ['siteaccess' => $admin, 'url' => $this->router->generate(
                'ibexa.content.view',
                ['contentId' => $location->getContentInfo()->getId(), 'locationId' => $location->getId(), 'siteaccess' => $admin],
                UrlGeneratorInterface::ABSOLUTE_URL,
            )];
        } catch (Throwable) {
            return null;
        }
    }

    /** In an admin dedicated to a site, only the sites of that admin: the global admin keeps them all. */
    private function ofTheCurrentAdmin(array $names): array
    {
        $current = $this->siteAccesses->getCurrent()?->name;
        if ($current === null || !$this->isAdmin($current)) {
            return $names;
        }
        $domain = $this->domain($current);
        if ($domain === null || $domain === $this->domain('default')) {
            return $names;
        }

        return array_values(array_filter($names, fn (string $name): bool => $this->domain($name) === $domain));
    }

    private function adminFor(?string $front): ?string
    {
        $current = $this->siteAccesses->getCurrent()?->name;
        $admins = [];
        foreach ($this->siteAccesses->getAll() as $candidate) {
            if ($this->isAdmin($candidate->name)) {
                $admins[] = $candidate->name;
            }
        }
        // The one the user is in comes first.
        usort($admins, static fn (string $a, string $b): int => [$a !== $current, $a] <=> [$b !== $current, $b]);

        if ($front !== null) {
            $domain = $this->domain($front);
            foreach ($admins as $admin) {
                if ($domain !== null && $this->domain($admin) === $domain) {
                    return $admin;
                }
            }
        }

        return $current !== null && $this->isAdmin($current) ? $current : ($admins[0] ?? null);
    }

    private function isAdmin(string $siteaccess): bool
    {
        return in_array($this->adminGroup, $this->groups[$siteaccess] ?? [], true);
    }

    /** The domain of the session cookie of the siteaccess. */
    private function domain(string $siteaccess): ?string
    {
        try {
            $session = $this->config->getParameter('session', null, $siteaccess);
        } catch (Throwable) {
            return null;
        }

        return is_array($session) && is_string($session['cookie_domain'] ?? null) && $session['cookie_domain'] !== '' ? $session['cookie_domain'] : null;
    }
}
