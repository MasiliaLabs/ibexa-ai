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
            ->end();

        return $builder;
    }
}
