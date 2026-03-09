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
use Symfony\Component\Yaml\Yaml;

#[AsCommand(
    name: 'ossmb:opensign:setup',
    description: 'Bootstrap OpenSign: complete system setup (user, tenant, organization, team, profile).',
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
        $io->title('OpenSign Full Bootstrap Setup');

        $envFileArg = $input->getArgument('envFile');
        $envFile = is_string($envFileArg) ? $envFileArg : '.env.dist';

        $configPath = $this->rootPath . '/config/opensign_setup.yaml';
        if (!file_exists($configPath)) {
            $io->error(sprintf('Configuration file not found: %s', $configPath));

            return Command::FAILURE;
        }

        /** @var array<string, array<string, string|bool>> $config */
        $config = Yaml::parseFile($configPath);

        try {
            $headers = [
                'X-Parse-Application-Id' => $this->appId,
                'X-Parse-Master-Key' => $this->masterKey,
                'Content-Type' => 'application/json',
            ];

            // 1. Create Admin User
            $io->section('1. Creating System Admin User');
            $response = $this->client->request('POST', $this->apiUrl . '/users', [
                'headers' => $headers,
                'json' => [
                    'username' => $config['admin']['username'],
                    'password' => $config['admin']['password'],
                    'email' => $config['admin']['email'],
                    'name' => $config['admin']['name'],
                    'phone' => $config['admin']['phone'],
                ],
                'http_errors' => false,
            ]);

            $userData = json_decode($response->getBody()->getContents(), true);
            $userId = $userData['objectId'] ?? null;

            if (!$userId && isset($userData['code']) && $userData['code'] === 202) {
                $io->note('User already exists, attempting login to get token...');
                $response = $this->client->request('POST', $this->apiUrl . '/login', [
                    'headers' => $headers,
                    'json' => [
                        'username' => $config['admin']['username'],
                        'password' => $config['admin']['password'],
                    ],
                ]);
                $loginData = json_decode($response->getBody()->getContents(), true);
                $userId = $loginData['objectId'];
                $sessionToken = $loginData['sessionToken'];
            } else {
                $sessionToken = $userData['sessionToken'] ?? '';
            }

            $io->success('User ID: ' . $userId);

            // 2. Create Tenant
            $io->section('2. Creating Tenant');
            $response = $this->client->request('POST', $this->apiUrl . '/classes/partners_Tenant', [
                'headers' => $headers,
                'json' => [
                    'TenantName' => $config['admin']['company'],
                    'companyName' => $config['admin']['company'],
                    'EmailAddress' => $config['admin']['email'],
                    'ContactNumber' => $config['admin']['phone'],
                    'IsActive' => true,
                    'UserId' => [
                        '__type' => 'Pointer',
                        'className' => '_User',
                        'objectId' => $userId,
                    ],
                    'CreatedBy' => [
                        '__type' => 'Pointer',
                        'className' => '_User',
                        'objectId' => $userId,
                    ],
                ],
            ]);
            $tenantData = json_decode($response->getBody()->getContents(), true);
            $tenantId = $tenantData['objectId'];
            $io->success('Tenant ID: ' . $tenantId);

            // 3. Create Organizations
            $io->section('3. Creating Organization');
            $response = $this->client->request('POST', $this->apiUrl . '/classes/contracts_Organizations', [
                'headers' => $headers,
                'json' => [
                    'Name' => $config['admin']['company'],
                    'IsActive' => true,
                    'CreatedBy' => [
                        '__type' => 'Pointer',
                        'className' => '_User',
                        'objectId' => $userId,
                    ],
                    'TenantId' => [
                        '__type' => 'Pointer',
                        'className' => 'partners_Tenant',
                        'objectId' => $tenantId,
                    ],
                ],
            ]);
            $orgData = json_decode($response->getBody()->getContents(), true);
            $orgId = $orgData['objectId'];
            $io->success('Organization ID: ' . $orgId);

            // 4. Create Team (All Users)
            $io->section('4. Creating Team');
            $response = $this->client->request('POST', $this->apiUrl . '/classes/contracts_Teams', [
                'headers' => $headers,
                'json' => [
                    'Name' => 'All Users',
                    'IsActive' => true,
                    'OrganizationId' => [
                        '__type' => 'Pointer',
                        'className' => 'contracts_Organizations',
                        'objectId' => $orgId,
                    ],
                ],
            ]);
            $teamData = json_decode($response->getBody()->getContents(), true);
            $teamId = $teamData['objectId'];

            // Self link for ancestors
            $this->client->request('PUT', $this->apiUrl . '/classes/contracts_Teams/' . $teamId, [
                'headers' => $headers,
                'json' => [
                    'Ancestors' => [
                        [
                            '__type' => 'Pointer',
                            'className' => 'contracts_Teams',
                            'objectId' => $teamId,
                        ],
                    ],
                ],
            ]);
            $io->success('Team ID: ' . $teamId);

            // 5. Create Profile (contracts_Users)
            $io->section('5. Creating Profile (contracts_Users)');
            $response = $this->client->request('POST', $this->apiUrl . '/classes/contracts_Users', [
                'headers' => $headers,
                'json' => [
                    'Name' => $config['admin']['name'],
                    'Email' => $config['admin']['email'],
                    'Phone' => $config['admin']['phone'],
                    'Company' => $config['admin']['company'],
                    'JobTitle' => $config['admin']['job_title'],
                    'UserRole' => 'contracts_Admin',
                    'Timezone' => $config['admin']['timezone'],
                    'UserId' => [
                        '__type' => 'Pointer',
                        'className' => '_User',
                        'objectId' => $userId,
                    ],
                    'TenantId' => [
                        '__type' => 'Pointer',
                        'className' => 'partners_Tenant',
                        'objectId' => $tenantId,
                    ],
                    'OrganizationId' => [
                        '__type' => 'Pointer',
                        'className' => 'contracts_Organizations',
                        'objectId' => $orgId,
                    ],
                    'TeamIds' => [
                        [
                            '__type' => 'Pointer',
                            'className' => 'contracts_Teams',
                            'objectId' => $teamId,
                        ],
                    ],
                    'TourStatus' => [['loginTour' => true]],
                ],
            ]);
            $profileData = json_decode($response->getBody()->getContents(), true);
            $profileId = $profileData['objectId'];

            // Update Organization with ExtUserId
            $this->client->request('PUT', $this->apiUrl . '/classes/contracts_Organizations/' . $orgId, [
                'headers' => $headers,
                'json' => [
                    'ExtUserId' => [
                        '__type' => 'Pointer',
                        'className' => 'contracts_Users',
                        'objectId' => $profileId,
                    ],
                ],
            ]);
            $io->success('Profile ID: ' . $profileId);

            // 6. Fix user (Tenant link & Admin flags)
            $io->section('6. Updating User metadata');
            $this->client->request('PUT', $this->apiUrl . '/users/' . $userId, [
                'headers' => $headers,
                'json' => [
                    'TenantID' => $tenantId, // Legacy mapping in some versions
                    'IsAdmin' => true,
                    'IsActive' => true,
                ],
            ]);
            $io->success('User metadata updated.');

            // 7. Initial Schema (contracts_Document)
            $io->section('7. Initializing Schema');
            $this->client->request('POST', $this->apiUrl . '/schemas/contracts_Document', [
                'headers' => $headers,
                'json' => [
                    'fields' => [
                        'Name' => ['type' => 'String'],
                        'URL' => ['type' => 'String'],
                        'Signers' => ['type' => 'Array'],
                        'Status' => ['type' => 'String'],
                        'CreatedBy' => ['type' => 'Pointer', 'targetClass' => '_User'],
                    ],
                ],
                'http_errors' => false,
            ]);
            $io->success('Schema initialized.');

            // 8. Updating Env
            $io->section('8. Updating Environment File: ' . $envFile);
            $filePath = realpath($this->rootPath . '/' . $envFile);
            if ($filePath) {
                $editor = DotEnvEditor::load($filePath, false);
                $editor->set('OPENSIGN_USER_ID', $userId);
                $editor->set('OPENSIGN_SESSION_TOKEN', $sessionToken);
                $editor->set('OPENSIGN_API_USERNAME', $config['admin']['username']);
                $editor->set('OPENSIGN_API_PASSWORD', $config['admin']['password']);
                $editor->write();
                $io->success('Environment variables updated in ' . $envFile);
            } else {
                $io->warning('Could not find ' . $envFile . '. Please update it manually.');
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Full setup failed: ' . $e->getMessage());

            return Command::FAILURE;
        }
    }
}
