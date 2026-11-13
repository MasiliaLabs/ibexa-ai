<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi;

use Masilia\Bundle\IbexaAi\Agent\Tool\ToolInterface;
use Masilia\Bundle\IbexaAi\Os\Security\OsPolicyProvider;
use Masilia\IbexaAi\Os\Capability;
use Masilia\IbexaAi\Os\Skill;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class MasiliaIbexaAiBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        // Packages that sit on the AI OS (see Skill) get their capabilities and skills registered by implementing the
        // interface: no tag to write in their own services file.
        $container->registerForAutoconfiguration(Capability::class)->addTag('masilia.ibexa_ai.capability');
        $container->registerForAutoconfiguration(Skill::class)->addTag('masilia.ibexa_ai.skill');
        $container->registerForAutoconfiguration(ToolInterface::class)->addTag('masilia.ibexa_ai.tool');

        // The "masilia_ai" permissions: who may let the assistant write, remove, change access.
        /** @var \Ibexa\Bundle\Core\DependencyInjection\IbexaCoreExtension $core */
        $core = $container->getExtension('ibexa');
        $core->addPolicyProvider(new OsPolicyProvider());
    }
}
