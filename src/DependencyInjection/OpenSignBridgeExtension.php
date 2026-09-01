<?php

declare(strict_types=1);

namespace Mosl\OpenSignBridgeBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class OpenSignBridgeExtension extends Extension
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

        $opensign = is_array($config['opensign'] ?? null) ? $config['opensign'] : [];

        // Note: According to the development guidelines, we do not set default empty values.
        // We ensure these parameters are always passed down properly so validation fails if empty.
        $container->setParameter('opensign_bridge.opensign.app_id', $this->stringOrNull($opensign['app_id'] ?? null));
        $container->setParameter(
            'opensign_bridge.opensign.master_key',
            $this->stringOrNull($opensign['master_key'] ?? null)
        );
        $container->setParameter('opensign_bridge.opensign.api_url', $this->stringOrNull($opensign['api_url'] ?? null));
        $container->setParameter('opensign_bridge.opensign.user_id', $this->stringOrNull($opensign['user_id'] ?? null));
        $container->setParameter(
            'opensign_bridge.opensign.session_token',
            $this->stringOrNull($opensign['session_token'] ?? null)
        );
        $container->setParameter(
            'opensign_bridge.webhook_secret',
            $this->stringOrNull($config['webhook_secret'] ?? null) ?? ''
        );
    }

    private function stringOrNull(mixed $value): ?string
    {
        return is_scalar($value) ? (string) $value : null;
    }
}
