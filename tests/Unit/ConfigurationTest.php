<?php

declare(strict_types=1);

namespace Mosl\OpenSignBridgeBundle\Tests\Unit;

use Mosl\OpenSignBridgeBundle\DependencyInjection\Configuration;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ConfigurationTest extends KernelTestCase
{
    public function testConfigTreeBuilds(): void
    {
        $configuration = new Configuration();
        $treeBuilder = $configuration->getConfigTreeBuilder();
        $this->assertSame('open_sign_bridge', $treeBuilder->buildTree()->getName());
    }
}
