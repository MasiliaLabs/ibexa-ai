<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Ibexa\Site;

use Ibexa\Contracts\Core\SiteAccess\ConfigResolverInterface;
use Ibexa\Core\MVC\Symfony\SiteAccess\SiteAccessServiceInterface;
use Throwable;

/**
 * The content languages of a site.
 *
 * A site is served by several siteaccesses on the same content tree, one per language (for example
 * shop in English, shop_fr in French). The languages a site can be written in are the ones
 * of all the siteaccesses that share its tree root, the siteaccess asked for first: its own language is the
 * site's language for that siteaccess.
 */
readonly class SiteLanguages
{
    public function __construct(
        private ConfigResolverInterface $configResolver,
        private SiteAccessServiceInterface $siteAccesses,
    ) {
    }

    /**
     * @return list<string> Language codes, the siteaccess's own main language first; empty when unknown
     */
    public function forSiteaccess(string $siteaccess): array
    {
        $own = $this->languagesOf($siteaccess);
        $root = $this->rootOf($siteaccess);
        if ($root === null) {
            return $own;
        }

        $languages = $own;
        foreach ($this->siteAccesses->getAll() as $other) {
            $name = $other->name;
            if ($name === $siteaccess || $this->rootOf($name) !== $root) {
                continue;
            }
            foreach ($this->languagesOf($name) as $code) {
                if (!in_array($code, $languages, true)) {
                    $languages[] = $code;
                }
            }
        }

        return $languages;
    }

    /**
     * @return list<string>
     */
    private function languagesOf(string $siteaccess): array
    {
        try {
            $languages = $this->configResolver->getParameter('languages', null, $siteaccess !== '' ? $siteaccess : null);
        } catch (Throwable) {
            return [];
        }

        return is_array($languages) ? array_values(array_filter($languages, 'is_string')) : [];
    }

    private function rootOf(string $siteaccess): ?int
    {
        try {
            $root = $this->configResolver->getParameter('content.tree_root.location_id', null, $siteaccess !== '' ? $siteaccess : null);
        } catch (Throwable) {
            return null;
        }

        return is_numeric($root) && (int) $root > 0 ? (int) $root : null;
    }
}
