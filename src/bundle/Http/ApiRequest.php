<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Http;

use Ibexa\Contracts\Core\Repository\PermissionResolver;
use Ibexa\Core\MVC\Symfony\SiteAccess\SiteAccessServiceInterface;
use Symfony\Component\HttpFoundation\Request;

/** What the endpoints of the admin API each need to read from the request: its JSON body, the siteaccess and the user. */
readonly class ApiRequest
{
    public function __construct(
        private PermissionResolver $permissions,
        private SiteAccessServiceInterface $siteAccess,
    ) {
    }

    /**
     * The JSON object of the body, or null when the body is not JSON or not an object.
     *
     * @return array<string, mixed>|null
     */
    public function json(Request $request): ?array
    {
        try {
            $decoded = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }

    /** The name of the siteaccess the request is served by. */
    public function siteaccess(): string
    {
        return $this->siteAccess->getCurrent()?->name ?? 'default';
    }

    public function userId(): int
    {
        return (int) $this->permissions->getCurrentUserReference()->getUserId();
    }
}
