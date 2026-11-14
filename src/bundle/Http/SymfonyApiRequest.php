<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Http;

use Ibexa\Contracts\Core\Repository\PermissionResolver;
use Masilia\Contracts\IbexaAi\Http\ApiRequest;
use Ibexa\Core\MVC\Symfony\SiteAccess\SiteAccessServiceInterface;
use Symfony\Component\HttpFoundation\Request;

/** {@see ApiRequest} on the Symfony request, the Ibexa siteaccess and the current user. */
final readonly class SymfonyApiRequest implements ApiRequest
{
    public function __construct(
        private PermissionResolver $permissions,
        private SiteAccessServiceInterface $siteAccess,
    ) {
    }

    public function json(Request $request): ?array
    {
        try {
            $decoded = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }

    public function siteaccess(): string
    {
        return $this->siteAccess->getCurrent()?->name ?? 'default';
    }

    public function userId(): int
    {
        return (int) $this->permissions->getCurrentUserReference()->getUserId();
    }
}
