<?php

declare(strict_types=1);

namespace Mosl\OpenSignBridgeBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('open_sign_bridge');

        /** @var ArrayNodeDefinition $rootNode */
        $rootNode = $treeBuilder->getRootNode();
        $rootNode
            ->children()
            ->arrayNode('opensign')
            ->children()
            ->scalarNode('app_id')
            ->defaultNull()
            ->end()
            ->scalarNode('master_key')
            ->defaultNull()
            ->end()
            ->scalarNode('api_url')
            ->defaultNull()
            ->end()
            ->scalarNode('user_id')
            ->defaultNull()
            ->end()
            ->scalarNode('session_token')
            ->defaultNull()
            ->end()
            ->end()
            ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
