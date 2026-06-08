<?php

declare(strict_types=1);

namespace Ossm\OssmBridgeBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class OssmBridgeExtension extends Extension
{
    /**
     * @param array<mixed> $configs
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../../config'));
        $loader->load('services.yaml');

        // Note: According to the development guidelines, we do not set default empty values.
        // We ensure these parameters are always passed down properly so validation fails if empty.
        $container->setParameter('ossm_bridge.opensign.app_id', $config['opensign']['app_id'] ?? null);
        $container->setParameter('ossm_bridge.opensign.master_key', $config['opensign']['master_key'] ?? null);
        $container->setParameter('ossm_bridge.opensign.api_url', $config['opensign']['api_url'] ?? null);
        $container->setParameter('ossm_bridge.opensign.user_id', $config['opensign']['user_id'] ?? null);
        $container->setParameter('ossm_bridge.opensign.session_token', $config['opensign']['session_token'] ?? null);
        $container->setParameter('ossm_bridge.webhook_secret', $config['webhook_secret']);
    }
}
