<?php

declare(strict_types=1);

namespace Ossm\OssmBridgeBundle\Command;

use Digimax\DotEnvEditor\DotEnvEditor;
use GuzzleHttp\ClientInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'ossmb:opensign:setup',
    description: 'Bootstrap OpenSign: creating user, getting session token, and initializing schema.',
)]
class OpenSignSetupCommand extends Command
{
    private ClientInterface $client;

    private string $appId;

    private string $masterKey;

    private string $apiUrl;

    private string $rootPath;

    public function __construct(
        ClientInterface $client,
        string $appId,
        string $masterKey,
        string $apiUrl,
        string $rootPath
    ) {
        $this->client = $client;
        $this->appId = $appId;
        $this->masterKey = $masterKey;
        $this->apiUrl = rtrim($apiUrl, '/');
        $this->rootPath = $rootPath;

        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('envFile', InputArgument::OPTIONAL, 'The .env file to update', '.env');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $envFileArg = $input->getArgument('envFile');
        $envFile = is_string($envFileArg) ? $envFileArg : '.env.dist';

        $io->title('OpenSign Bootstrap Setup');

        try {
            $io->section('Creating API System User');
            $response = $this->client->request('POST', $this->apiUrl . '/users', [
                'headers' => [
                    'X-Parse-Application-Id' => $this->appId,
                    'X-Parse-Master-Key' => $this->masterKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'username' => 'api-system@ossmb.com',
                    'password' => 'api-password-123',
                    'email' => 'api-system@ossmb.com',
                    'name' => 'API System User',
                ],
                'http_errors' => false,
            ]);

            /** @var array<string, mixed> $userData */
            $userData = json_decode($response->getBody()->getContents(), true);
            /** @var string|null $userId */
            $userId = $userData['objectId'] ?? null;

            if (! $userId && isset($userData['code']) && $userData['code'] === 202) {
                $io->note('User already exists, attempting login to get token...');
            }

            $io->section('Retrieving Session Token');
            $response = $this->client->request('POST', $this->apiUrl . '/login', [
                'headers' => [
                    'X-Parse-Application-Id' => $this->appId,
                    'X-Parse-Master-Key' => $this->masterKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'username' => 'api-system@ossmb.com',
                    'password' => 'api-password-123',
                ],
            ]);

            /** @var array<string, mixed> $loginData */
            $loginData = json_decode($response->getBody()->getContents(), true);
            /** @var string $userId */
            $userId = $loginData['objectId'];
            /** @var string $sessionToken */
            $sessionToken = $loginData['sessionToken'];

            $io->success('User ID: ' . $userId);
            $io->success('Session Token: ' . $sessionToken);

            $io->section('Initializing Tenant');
            $this->client->request('POST', $this->apiUrl . '/classes/partners_Tenant', [
                'headers' => [
                    'X-Parse-Application-Id' => $this->appId,
                    'X-Parse-Master-Key' => $this->masterKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'Domain' => null,
                    'companyName' => 'OSSM Bridge Bundle Dev',
                ],
                'http_errors' => false,
            ]);

            $io->section('Initializing Schema');
            $this->client->request('POST', $this->apiUrl . '/schemas/contracts_Document', [
                'headers' => [
                    'X-Parse-Application-Id' => $this->appId,
                    'X-Parse-Master-Key' => $this->masterKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'fields' => [
                        'Name' => [
                            'type' => 'String',
                        ],
                        'URL' => [
                            'type' => 'String',
                        ],
                        'Signers' => [
                            'type' => 'Array',
                        ],
                        'Status' => [
                            'type' => 'String',
                        ],
                        'CreatedBy' => [
                            'type' => 'Pointer',
                            'targetClass' => '_User',
                        ],
                    ],
                ],
                'http_errors' => false,
            ]);
            $io->success('Schema initialized.');

            $io->section('Updating Environment File: ' . $envFile);
            $filePath = realpath($this->rootPath . '/' . $envFile);
            if ($filePath) {
                $editor = DotEnvEditor::load($filePath, false);
                $editor->set('OPENSIGN_USER_ID', $userId);
                $editor->set('OPENSIGN_SESSION_TOKEN', $sessionToken);
                $editor->write();
                $io->success('Environment variables updated in ' . $envFile);
            } else {
                $io->warning('Could not find ' . $envFile . '. Please update it manually.');
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Setup failed: ' . $e->getMessage());

            return Command::FAILURE;
        }
    }
}
