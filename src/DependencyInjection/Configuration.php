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
            ->scalarNode('webhook_secret')
            ->info(
                'Shared secret used to verify the x-webhook-signature header on incoming OpenSign webhook calls (HMAC-SHA256). Required.'
            )
            ->isRequired()
            ->cannotBeEmpty()
            ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
