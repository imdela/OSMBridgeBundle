<?php

declare(strict_types=1);

namespace Ossm\OssmBridgeBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('ossm_bridge');

        /** @var ArrayNodeDefinition $rootNode */
        $rootNode = $treeBuilder->getRootNode();
        $rootNode
            ->children()
            ->arrayNode('opensign')
            ->children()
            ->scalarNode('app_id')
            ->isRequired()
            ->cannotBeEmpty()
            ->end()
            ->scalarNode('master_key')
            ->isRequired()
            ->cannotBeEmpty()
            ->end()
            ->scalarNode('api_url')
            ->isRequired()
            ->cannotBeEmpty()
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
