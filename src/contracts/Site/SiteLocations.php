<?php

declare(strict_types=1);

namespace Masilia\Contracts\IbexaAi\Site;

/** Where the sites of the installation are in the content tree. */
interface SiteLocations
{
    /**
     * The location the content tree of a siteaccess starts at (content.tree_root.location_id).
     *
     * @param string $siteaccess A siteaccess name; the current siteaccess when empty
     *
     * @return int|null Null for a siteaccess that does not exist
     */
    public function rootLocationId(string $siteaccess = ''): ?int;
}
