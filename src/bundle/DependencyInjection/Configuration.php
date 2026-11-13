<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $builder = new TreeBuilder('masilia_ibexa_ai');
        $root = $builder->getRootNode();

        $root
            ->children()
                ->arrayNode('connection')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('provider')
                            ->defaultNull()
                            ->info('openai, anthropic, gemini, mistral, ollama, qwen, kimi, minimax or mimo')
                        ->end()
                        ->scalarNode('model')->defaultValue('')->end()
                        ->scalarNode('api_key')->defaultNull()->end()
                        ->scalarNode('api_url')->defaultNull()->end()
                        ->floatNode('temperature')->defaultValue(0.7)->min(0.0)->max(2.0)->end()
                        ->integerNode('max_tokens')->defaultValue(4096)->min(1)->end()
                    ->end()
                ->end()
                ->arrayNode('os')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('domains')
                            ->info('Turns the capabilities of a domain (content, trash, structure, access…) on or off for everyone. A domain not listed is on, except "access" (roles, policies, users), which is off until it is turned on here.')
                            ->useAttributeAsKey('name')
                            ->booleanPrototype()->end()
                            ->defaultValue(['access' => false])
                        ->end()
                    ->end()
                ->end()
                ->scalarNode('encryption_key')
                    ->defaultNull()
                    ->info('Key that encrypts the provider API keys stored in the database: base64 of 32 random bytes (php -r "echo base64_encode(random_bytes(32));"). When empty, the key is derived from the application secret, so changing that secret makes the stored keys unreadable.')
                ->end()
            ->end();

        return $builder;
    }
}
