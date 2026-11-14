<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Security;

use Ibexa\Bundle\Core\DependencyInjection\Security\PolicyProvider\YamlPolicyProvider;

/**
 * The permissions of the assistant, as Ibexa policies of the module "masilia_ai", so that they are given by role in
 * the admin like any other: use (read), write, destructive, structure, access.
 */
final class OsPolicyProvider extends YamlPolicyProvider
{
    protected function getFiles(): array
    {
        return [__DIR__ . '/../Resources/config/policies.yaml'];
    }
}
