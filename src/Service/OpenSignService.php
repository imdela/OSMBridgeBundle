<?php

declare(strict_types=1);

namespace Mosl\OpenSignBridgeBundle\Service;

use GuzzleHttp\ClientInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Service to interact with OpenSign Parse Server API.
 */
class OpenSignService
{
    private ClientInterface $client;

    private string $appId;

    private string $masterKey;

    private string $apiUrl;

    private string $userId;

    private string $sessionToken;

    public function __construct(
        ClientInterface $client,
        ?string $appId = null,
        ?string $masterKey = null,
        ?string $apiUrl = null,
        ?string $userId = null,
        ?string $sessionToken = null
    ) {
        $this->client = $client;
        $this->appId = $appId ?? '';
        $this->masterKey = $masterKey ?? '';
        $this->apiUrl = rtrim($apiUrl ?? '', '/');
        $this->userId = $userId ?? '';
        $this->sessionToken = $sessionToken ?? '';
    }

    /**
     * Uploads a file to OpenSign by streaming it directly from the disk.
     *
     * @return array<string, mixed>
     */
    public function uploadFile(string $fileName, string $filePath, string $mimeType): array
    {
        if (! file_exists($filePath) || ! is_readable($filePath)) {
            throw new \InvalidArgumentException(sprintf('File not found or not readable: %s', $filePath));
        }

        $resource = fopen($filePath, 'r');
        if ($resource === false) {
            throw new \RuntimeException(sprintf('Could not open file: %s', $filePath));
        }

        $response = $this->client->request('POST', sprintf('%s/files/%s', $this->apiUrl, $fileName), [
            'headers' => [
                'X-Parse-Application-Id' => $this->appId,
                'X-Parse-Master-Key' => $this->masterKey,
                'Content-Type' => $mimeType,
            ],
            'body' => $resource,
        ]);

        if ($response->getStatusCode() !== Response::HTTP_CREATED) {
            throw new HttpException($response->getStatusCode(), 'Failed to upload file to OpenSign');
        }

        /** @var array<string, mixed> $data */
        $data = json_decode($response->getBody()->getContents(), true);

        return $data;
    }

    /**
     * Helper to get the API user's profile ID (ExtUserPtr).
     */
    public function getExtUserProfileId(): ?string
    {
        $query = [
            'where' => json_encode([
                'UserId' => [
                    '__type' => 'Pointer',
                    'className' => '_User',
                    'objectId' => $this->userId,
                ],
            ]),
        ];

        $response = $this->client->request(
            'GET',
            sprintf('%s/classes/contracts_Users?%s', $this->apiUrl, http_build_query($query)),
            [
                'headers' => [
                    'X-Parse-Application-Id' => $this->appId,
                    'X-Parse-Master-Key' => $this->masterKey,
                ],
            ]
        );

        $content = $response->getBody()
            ->getContents();
        $data = json_decode($content, true);

        if (! is_array($data) || ! isset($data['results']) || ! is_array($data['results'])) {
            return null;
        }

        /** @var array<int, array<string, mixed>> $results */
        $results = $data['results'];

        return isset($results[0]['objectId']) && is_string($results[0]['objectId']) ? $results[0]['objectId'] : null;
    }

    /**
     * Creates or retrieves a Contactbook entry for a guest signer.
     */
    public function createGuestSigner(string $email, string $name, ?string $phone = null): string
    {
        // 1. Create or Fetch User
        $userResponse = $this->client->request('POST', sprintf('%s/users', $this->apiUrl), [
            'headers' => [
                'X-Parse-Application-Id' => $this->appId,
                'X-Parse-Master-Key' => $this->masterKey,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'username' => $email,
                'email' => $email,
                'password' => $email, // Default placeholder password
                'name' => $name,
            ],
            'http_errors' => false,
        ]);

        $content = $userResponse->getBody()
            ->getContents();
        $userData = json_decode($content, true);
        $signerUserId = is_array($userData) && isset($userData['objectId']) && is_string(
            $userData['objectId']
        ) ? $userData['objectId'] : null;

        if (! $signerUserId && is_array($userData) && isset($userData['code']) && $userData['code'] === 202) {
            $getRes = $this->client->request(
                'GET',
                sprintf('%s/users?where=%s', $this->apiUrl, urlencode((string) json_encode([
                    'email' => $email,
                ]))),
                [
                    'headers' => [
                        'X-Parse-Application-Id' => $this->appId,
                        'X-Parse-Master-Key' => $this->masterKey,
                    ],
                ]
            );

            $foundContent = $getRes->getBody()
                ->getContents();
            $userFound = json_decode($foundContent, true);

            if (is_array($userFound) && isset($userFound['results']) && is_array($userFound['results'])) {
                /** @var array<int, array<string, mixed>> $resArray */
                $resArray = $userFound['results'];
                if (isset($resArray[0]['objectId']) && is_string($resArray[0]['objectId'])) {
                    $signerUserId = $resArray[0]['objectId'];
                }
            }
        }

        if (! $signerUserId) {
            throw new \RuntimeException(sprintf('Could not create or find user for signer email: %s', $email));
        }

        // 2. Check if Contactbook entry already exists
        $contactQuery = [
            'where' => json_encode([
                'CreatedBy' => [
                    '__type' => 'Pointer',
                    'className' => '_User',
                    'objectId' => $this->userId,
                ],
                'Email' => $email,
                'IsDeleted' => [
                    '$ne' => true,
                ],
            ]),
        ];

        $getContactRes = $this->client->request(
            'GET',
            sprintf('%s/classes/contracts_Contactbook?%s', $this->apiUrl, http_build_query($contactQuery)),
            [
                'headers' => [
                    'X-Parse-Application-Id' => $this->appId,
                    'X-Parse-Session-Token' => $this->sessionToken,
                ],
            ]
        );
        $contactContent = $getContactRes->getBody()
            ->getContents();
        $getContactData = json_decode($contactContent, true);

        if (is_array($getContactData) && ! empty($getContactData['results']) && is_array($getContactData['results'])) {
            $firstResult = $getContactData['results'][0];
            if (is_array($firstResult) && isset($firstResult['objectId']) && is_string($firstResult['objectId'])) {
                return $firstResult['objectId'];
            }
        }

        // 3. Create Contactbook Entry
        $contactPayload = [
            'Name' => $name,
            'Email' => $email,
            'UserRole' => 'contracts_Guest',
            'IsDeleted' => false,
            'CreatedBy' => [
                '__type' => 'Pointer',
                'className' => '_User',
                'objectId' => $this->userId,
            ],
            'UserId' => [
                '__type' => 'Pointer',
                'className' => '_User',
                'objectId' => $signerUserId,
            ],
        ];

        if ($phone) {
            $contactPayload['Phone'] = $phone;
        }

        $contactRes = $this->client->request('POST', sprintf('%s/classes/contracts_Contactbook', $this->apiUrl), [
            'headers' => [
                'X-Parse-Application-Id' => $this->appId,
                'X-Parse-Master-Key' => $this->masterKey,
                'Content-Type' => 'application/json',
            ],
            'json' => $contactPayload,
        ]);

        $createdContactContent = $contactRes->getBody()
            ->getContents();
        $contactData = json_decode($createdContactContent, true);

        if (! is_array($contactData) || ! isset($contactData['objectId']) || ! is_string($contactData['objectId'])) {
            throw new \RuntimeException('Failed to create Contactbook entry');
        }

        return $contactData['objectId'];
    }

    /**
     * Creates a signature request in OpenSign.
     *
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    public function createSignatureRequest(array $payload): array
    {
        $extProfileId = $this->getExtUserProfileId();

        if ($extProfileId) {
            $payload['ExtUserPtr'] = [
                '__type' => 'Pointer',
                'className' => 'contracts_Users',
                'objectId' => $extProfileId,
            ];
        }

        $payload['CreatedBy'] = [
            '__type' => 'Pointer',
            'className' => '_User',
            'objectId' => $this->userId,
        ];

        // Format placeholders and Pointers recursively if necessary
        // In a real usage, the caller should pass pre-formatted Signers array of Pointers
        // Ensure Status is draft or published based on requirement. OpenSign uses "draft" internally
        // mostly, but actually, documents ready to sign have "Status" "draft" and "Signers" set.

        $response = $this->client->request('POST', sprintf('%s/classes/contracts_Document', $this->apiUrl), [
            'headers' => [
                'X-Parse-Application-Id' => $this->appId,
                'X-Parse-Master-Key' => $this->masterKey,
                'X-Parse-Session-Token' => $this->sessionToken,
                'Content-Type' => 'application/json',
            ],
            'json' => $payload,
        ]);

        if ($response->getStatusCode() !== Response::HTTP_CREATED) {
            throw new HttpException($response->getStatusCode(), 'Failed to create signature request in OpenSign');
        }

        /** @var array<string, mixed> $data */
        $data = json_decode($response->getBody()->getContents(), true);

        return $data;
    }

    /**
     * Provisions a new user in OpenSign.
     *
     * @return array<string, mixed>
     */
    public function provisionUser(string $username, string $password, string $email, string $name): array
    {
        $response = $this->client->request('POST', sprintf('%s/users', $this->apiUrl), [
            'headers' => [
                'X-Parse-Application-Id' => $this->appId,
                'X-Parse-Master-Key' => $this->masterKey,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'username' => $username,
                'password' => $password,
                'email' => $email,
                'name' => $name,
            ],
        ]);

        if ($response->getStatusCode() !== Response::HTTP_CREATED) {
            throw new HttpException($response->getStatusCode(), 'Failed to provision user in OpenSign');
        }

        /** @var array<string, mixed> $data */
        $data = json_decode($response->getBody()->getContents(), true);

        return $data;
    }

    /**
     * Retrieves a document by its objectId.
     *
     * @return array<string, mixed>
     */
    public function getDocument(string $objectId): array
    {
        $response = $this->client->request(
            'GET',
            sprintf('%s/classes/contracts_Document/%s', $this->apiUrl, $objectId),
            [
                'headers' => [
                    'X-Parse-Application-Id' => $this->appId,
                    'X-Parse-Session-Token' => $this->sessionToken,
                ],
            ]
        );

        if ($response->getStatusCode() !== Response::HTTP_OK) {
            throw new HttpException($response->getStatusCode(), 'Failed to retrieve document from OpenSign');
        }

        /** @var array<string, mixed> $data */
        $data = json_decode($response->getBody()->getContents(), true);

        return $data;
    }

    /**
     * Applies a real cryptographic signature to a document via OpenSign's
     * own PDF cloud function (@signpdf/signer-p12 server-side, using the
     * server's configured PFX certificate). $pdfFile is the base64-encoded
     * original PDF; $signerId is the Signers[].objectId (Contactbook entry
     * from createGuestSigner) acting on the document.
     *
     * @return array<string, mixed>
     */
    public function signDocument(
        string $objectId,
        string $signerId,
        string $pdfFileBase64,
        string $signatureBase64 = ''
    ): array {
        $response = $this->client->request('POST', sprintf('%s/functions/PDF', $this->apiUrl), [
            'headers' => [
                'X-Parse-Application-Id' => $this->appId,
                'X-Parse-Master-Key' => $this->masterKey,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'docId' => $objectId,
                'userId' => $signerId,
                'pdfFile' => $pdfFileBase64,
                'signature' => $signatureBase64,
            ],
        ]);

        if ($response->getStatusCode() !== Response::HTTP_OK) {
            throw new HttpException($response->getStatusCode(), 'Failed to sign document in OpenSign');
        }

        /** @var array<string, mixed> $data */
        $data = json_decode($response->getBody()->getContents(), true);

        return $data;
    }
}
