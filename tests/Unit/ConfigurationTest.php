<?php

declare(strict_types=1);

namespace Ossm\OssmBridgeBundle\Tests\Unit;

use Ossm\OssmBridgeBundle\DependencyInjection\Configuration;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ConfigurationTest extends KernelTestCase
{
    public function testConfigTreeBuilds(): void
    {
        $configuration = new Configuration();
        $treeBuilder = $configuration->getConfigTreeBuilder();
        $this->assertSame('ossm_bridge', $treeBuilder->buildTree()->getName());
    }
}
