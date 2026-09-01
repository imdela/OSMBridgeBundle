<?php

declare(strict_types=1);

namespace Mosl\OpenSignBridgeBundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'opensignb:install',
    description: 'Automates the initial configuration: copies config templates and sets up environment variables.',
)]
class InstallCommand extends Command
{
    private string $rootPath;

    public function __construct(string $rootPath)
    {
        $this->rootPath = $rootPath;

        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('OpenSignBridgeBundle Installation Utility');

        // 1. Copy Configuration
        $io->section('1. Configuring Bundle (opensign_bridge.yaml)');
        $targetConfig = $this->rootPath . '/config/packages/opensign_bridge.yaml';
        $templateConfig = __DIR__ . '/../../resources/templates/opensign_bridge.yaml';

        if (! file_exists($templateConfig)) {
            $io->error('Template configuration not found in bundle.');

            return Command::FAILURE;
        }

        if (file_exists($targetConfig)) {
            if ($io->confirm('config/packages/opensign_bridge.yaml already exists. Overwrite?', false)) {
                copy($templateConfig, $targetConfig);
                $io->success('Configuration overwritten.');
            } else {
                $io->note('Skipped configuration copy.');
            }
        } else {
            if (! is_dir(dirname($targetConfig))) {
                mkdir(dirname($targetConfig), 0777, true);
            }
            copy($templateConfig, $targetConfig);
            $io->success('Created config/packages/opensign_bridge.yaml');
        }

        // 1.1 Copy Setup Configuration
        if ($io->confirm('Copy opensign_setup.yaml to host app configuration (needed for setup command)?', true)) {
            $io->section('1.1 Configuring Bootstrap (opensign_setup.yaml)');
            $targetSetup = $this->rootPath . '/config/opensign_setup.yaml';
            $templateSetup = __DIR__ . '/../../config/opensign_setup.yaml';
            if (file_exists($templateSetup)) {
                if (! file_exists($targetSetup) || $io->confirm(
                    'config/opensign_setup.yaml already exists. Overwrite?',
                    false
                )) {
                    if (! is_dir(dirname($targetSetup))) {
                        mkdir(dirname($targetSetup), 0777, true);
                    }
                    copy($templateSetup, $targetSetup);
                    $io->success('Created config/opensign_setup.yaml');
                }
            }
        }

        // 2. Add Routes
        $io->section('2. Enabling Webhook Routes');
        $targetRoutes = $this->rootPath . '/config/routes/opensign_bridge.yaml';
        if (! file_exists($targetRoutes)) {
            if (! is_dir(dirname($targetRoutes))) {
                mkdir(dirname($targetRoutes), 0777, true);
            }
            file_put_contents(
                $targetRoutes,
                "opensign_bridge_routes:\n  resource: \"@OpenSignBridgeBundle/config/routes.yaml\"\n"
            );
            $io->success('Enabled routes in config/routes/opensign_bridge.yaml');
        } else {
            $io->note('Routes already configured.');
        }

        // 3. Environment Variables
        $io->section('3. Setting up Environment Variables (.env)');
        $envPath = $this->rootPath . '/.env';
        if (file_exists($envPath)) {
            $envContent = file_get_contents($envPath);
            if ($envContent === false) {
                $io->error('Failed to read .env file.');

                return Command::FAILURE;
            }

            $vars = [
                'OPENSIGN_APP_ID' => 'myAppId',
                'OPENSIGN_MASTER_KEY' => 'myMasterKey',
                'OPENSIGN_API_URL' => 'http://localhost:8080/app',
                'OPENSIGN_USER_ID' => '',
                'OPENSIGN_SESSION_TOKEN' => '',
                'OPENSIGN_WEBHOOK_SECRET' => '',
            ];

            $toAppend = "\n###> mosl/opensign-bridge-bundle ###\n";
            $needed = false;
            foreach ($vars as $var => $val) {
                if (! str_contains($envContent, $var)) {
                    $toAppend .= $var . '=' . $val . "\n";
                    $needed = true;
                }
            }
            $toAppend .= "###< mosl/opensign-bridge-bundle ###\n";

            if ($needed) {
                file_put_contents($envPath, $toAppend, FILE_APPEND);
                $io->success('Appended variables to .env');
            } else {
                $io->note('Variables already exist in .env');
            }
        }

        // 4. Copy Scripts
        $io->section('4. Copying scripts');
        $scriptsDir = $this->rootPath . '/bin';
        $bundleScripts = __DIR__ . '/../../resources/scripts';
        if (is_dir($bundleScripts)) {
            if (! is_dir($scriptsDir)) {
                mkdir($scriptsDir, 0777, true);
            }
            $files = scandir($bundleScripts);
            if (is_array($files)) {
                foreach ($files as $file) {
                    if ($file === '.' || $file === '..') {
                        continue;
                    }
                    copy($bundleScripts . '/' . $file, $scriptsDir . '/' . $file);
                    chmod($scriptsDir . '/' . $file, 0755);
                    $io->success('Copied ' . $file . ' to bin/');
                }
            }
        }

        $io->success('Installation complete!');

        return Command::SUCCESS;
    }
}
