<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
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
                        'dir' => __DIR__ . '/../Entity',
                        'prefix' => 'Masilia\Bundle\IbexaAi\Entity',
                        'alias' => 'MasiliaIbexaAi',
                    ],
                ],
            ],
        ]);

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

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yaml');
    }
}
