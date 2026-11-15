<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Masilia\IbexaAi\Ibexa\Field\Input\NovaSeoMetasInput;
use Novactive\Bundle\eZSEOBundle\Core\FieldType\Metas\Type;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

final class MasiliaIbexaAiExtension extends Extension implements PrependExtensionInterface
{
    public function prepend(ContainerBuilder $container): void
    {
        // Entities of the bundle are mapped next to the host's App entities.
        $container->prependExtensionConfig('doctrine', [
            'orm' => [
                'mappings' => [
                    'MasiliaIbexaAi' => [
                        'type' => 'attribute',
                        'is_bundle' => false,
                        'dir' => __DIR__ . '/../../lib/Persistence/Entity',
                        'prefix' => 'Masilia\IbexaAi\Persistence\Entity',
                        'alias' => 'MasiliaIbexaAi',
                    ],
                ],
            ],
        ]);

        // The texts of the capabilities page (React) come from the translation domain of this bundle, published to the scripts of the admin.
        $container->prependExtensionConfig('bazinga_js_translation', ['active_domains' => ['masilia_ibexa_ai']]);

        $container->prependExtensionConfig('doctrine_migrations', [
            'migrations_paths' => [
                'Masilia\\Bundle\\IbexaAi\\Migrations' => __DIR__ . '/../Migrations',
            ],
        ]);
    }

    public function load(array $configs, ContainerBuilder $container): void
    {
        $config = $this->processConfiguration(new Configuration(), $configs);

        $container->setParameter('ibexa_ai.connection', $config['connection']);
        $container->setParameter('ibexa_ai.encryption_key', $config['encryption_key']);
        $container->setParameter('ibexa_ai.os.domains', $config['os']['domains']);

        $container->setParameter('masilia_ibexa_ai.os_coverage_file', __DIR__ . '/../Resources/config/os_coverage.yaml');
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yaml');

        // The Nova SEO field is only handled when its bundle is there.
        if (!class_exists(Type::class)) {
            $container->removeDefinition(NovaSeoMetasInput::class);
        }
    }
}
