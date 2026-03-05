<?php

declare(strict_types=1);

namespace App\Service;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
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
        string $appId,
        string $masterKey,
        string $apiUrl,
        ?string $userId = null,
        ?string $sessionToken = null
    ) {
        $this->client = $client;
        $this->appId = $appId;
        $this->masterKey = $masterKey;
        $this->apiUrl = rtrim($apiUrl, '/');
        $this->userId = $userId ?? '';
        $this->sessionToken = $sessionToken ?? '';
    }

    /**
     * Uploads a file to OpenSign and returns the Parse File result.
     *
     * @return array<string, mixed>
     *
     * @throws GuzzleException
     */
    public function uploadFile(string $fileName, string $content, string $mimeType): array
    {
        $response = $this->client->request('POST', sprintf('%s/files/%s', $this->apiUrl, $fileName), [
            'headers' => [
                'X-Parse-Application-Id' => $this->appId,
                'X-Parse-Master-Key' => $this->masterKey,
                'Content-Type' => $mimeType,
            ],
            'body' => $content,
        ]);

        if (Response::HTTP_CREATED !== $response->getStatusCode()) {
            throw new HttpException($response->getStatusCode(), 'Failed to upload file to OpenSign');
        }

        /** @var array<string, mixed> $data */
        $data = json_decode($response->getBody()->getContents(), true);

        return $data;
    }

    /**
     * Creates a signature request in OpenSign.
     * Note: This is a placeholder for the actual OpenSign signature request logic.
     * OpenSign usually requires creating a Document object and then a SignatureRequest.
     *
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     *
     * @throws GuzzleException
     */
    public function createSignatureRequest(array $payload): array
    {
        $payload['CreatedBy'] = [
            '__type' => 'Pointer',
            'className' => '_User',
            'objectId' => $this->userId,
        ];

        $response = $this->client->request('POST', sprintf('%s/classes/contracts_Document', $this->apiUrl), [
            'headers' => [
                'X-Parse-Application-Id' => $this->appId,
                'X-Parse-Session-Token' => $this->sessionToken,
                'Content-Type' => 'application/json',
            ],
            'json' => $payload,
        ]);

        if (Response::HTTP_CREATED !== $response->getStatusCode()) {
            throw new HttpException($response->getStatusCode(), 'Failed to create signature request in OpenSign');
        }

        /** @var array<string, mixed> $data */
        $data = json_decode($response->getBody()->getContents(), true);

        return $data;
    }
}
