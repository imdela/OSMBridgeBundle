<?php

declare(strict_types=1);

namespace Mosl\OpenSignBridgeBundle\Tests\Unit;

use GuzzleHttp\ClientInterface;
use Mosl\OpenSignBridgeBundle\Command\OpenSignSetupCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * These cases only cover the password guard, which runs before any network
 * call — the command returns FAILURE straight after validation, so no
 * OpenSign server or Guzzle mock is needed to exercise it.
 */
class OpenSignSetupCommandTest extends TestCase
{
    private string $rootDir;

    protected function setUp(): void
    {
        $this->rootDir = sys_get_temp_dir() . '/opensignb-setup-test-' . uniqid();
        mkdir($this->rootDir . '/config', 0777, true);
    }

    protected function tearDown(): void
    {
        $configPath = $this->rootDir . '/config/opensign_setup.yaml';
        if (file_exists($configPath)) {
            unlink($configPath);
        }
        rmdir($this->rootDir . '/config');
        rmdir($this->rootDir);
    }

    public function testRejectsThePlaceholderPassword(): void
    {
        $this->writeConfig('CHANGE-ME-BEFORE-RUNNING-opensignb-opensign-setup');

        $tester = $this->makeTester();
        $tester->execute([]);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('placeholder admin password', $tester->getDisplay());
    }

    public function testRejectsAWeakPassword(): void
    {
        $this->writeConfig('short1');

        $tester = $this->makeTester();
        $tester->execute([]);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('too weak', $tester->getDisplay());
    }

    public function testMissingConfigFileFails(): void
    {
        $tester = $this->makeTester();
        $tester->execute([]);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('Configuration file not found', $tester->getDisplay());
    }

    private function writeConfig(string $password): void
    {
        $yaml = <<<YAML
            admin:
              name: "API System User"
              email: "api.system@example.com"
              username: "api.system@example.com"
              password: "{$password}"
              phone: "+33123456789"
              company: "Test Co"
              job_title: "Administrator"
              industry: "Software"
              timezone: "Europe/Paris"
            tenant:
              name: "Test Co"
              is_active: true
            organization:
              name: "Test Co"
              is_active: true
            team:
              name: "All Users"
              is_active: true
            YAML;

        file_put_contents($this->rootDir . '/config/opensign_setup.yaml', $yaml);
    }

    private function makeTester(): CommandTester
    {
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->never())
            ->method('request');

        $command = new OpenSignSetupCommand($this->rootDir, $client, 'app', 'key', 'http://example.test');

        return new CommandTester($command);
    }
}
