<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi;

use Masilia\Bundle\IbexaAi\Os\Security\OsPolicyProvider;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class MasiliaIbexaAiBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        // The "masilia_ai" permissions: who may let the assistant write, remove, change access.
        /** @var \Ibexa\Bundle\Core\DependencyInjection\IbexaCoreExtension $core */
        $core = $container->getExtension('ibexa');
        $core->addPolicyProvider(new OsPolicyProvider());
    }
}
