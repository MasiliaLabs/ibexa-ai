<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Chat;

use Ibexa\Contracts\AdminUi\UI\Config\ProviderInterface;
use Ibexa\Contracts\Core\Repository\PermissionResolver;

/**
 * Tells the admin's scripts whether the user may use the assistant (window.ibexa.adminUiConfig.masiliaAi), so that its button is only
 * there for whoever can use it.
 */
readonly class ChatAccessConfig implements ProviderInterface
{
    public function __construct(private PermissionResolver $permissions)
    {
    }

    /** @return array{canUse: bool} */
    public function getConfig(): array
    {
        return ['canUse' => (bool) $this->permissions->hasAccess('masilia_ai', 'use')];
    }
}
