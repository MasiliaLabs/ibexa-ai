<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Ibexa\Rules;

use Ibexa\AdminUi\Specification\Location\IsContainer;
use Ibexa\AdminUi\Specification\Location\IsRoot;
use Ibexa\AdminUi\Specification\Location\IsWithinCopySubtreeLimit;
use Ibexa\Contracts\Core\Repository\LocationService;
use Ibexa\Contracts\Core\Repository\Values\Content\Location;
use Ibexa\Contracts\Core\SiteAccess\ConfigResolverInterface;
use Throwable;

/**
 * The rules the Ibexa admin applies before it moves, copies or removes a location, so that the assistant refuses what the
 * admin interface would refuse, with the same reasons. The specifications are the admin's own.
 */
readonly class LocationRules
{
    /** The limit the admin ships with (subtree_operations.copy_subtree.limit) when the siteaccess does not set one. */
    private const DEFAULT_COPY_LIMIT = 100;

    public function __construct(
        private LocationService $locations,
        private ConfigResolverInterface $config,
    ) {
    }

    /** A root location (the top of a tree) cannot be moved, copied, trashed or removed. */
    public function isRoot(Location $location): bool
    {
        return (new IsRoot())->isSatisfiedBy($location);
    }

    public function isContainer(Location $location): bool
    {
        return (new IsContainer())->isSatisfiedBy($location);
    }

    /** How many locations a subtree copy may hold; -1 for no limit, 0 for no subtree copy at all. */
    public function copyLimit(): int
    {
        try {
            return (int) $this->config->getParameter('subtree_operations.copy_subtree.limit');
        } catch (Throwable) {
            return self::DEFAULT_COPY_LIMIT;
        }
    }

    public function isWithinCopyLimit(Location $location): bool
    {
        return (new IsWithinCopySubtreeLimit($this->copyLimit(), $this->locations))->isSatisfiedBy($location);
    }

    /** Whether $inner is $outer or lies under it (the admin compares the paths the same way). */
    public function isInside(Location $inner, Location $outer): bool
    {
        return stripos($inner->getPathString(), $outer->getPathString()) !== false;
    }
}
