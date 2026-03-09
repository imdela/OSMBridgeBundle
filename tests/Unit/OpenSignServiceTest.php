<?php

declare(strict_types=1);

namespace Ossm\OssmBridgeBundle\Tests\Unit;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use Ossm\OssmBridgeBundle\Service\OpenSignService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class OpenSignServiceTest extends TestCase
{
    /**
     * @var ClientInterface&MockObject
     */
    private ClientInterface $client;

    private OpenSignService $service;

    protected function setUp(): void
    {
        $this->client = $this->createMock(ClientInterface::class);
        $this->service = new OpenSignService(
            $this->client,
            'test-app-id',
            'test-master-key',
            'http://test-api-url',
            'test-user-id',
            'test-session-token'
        );
    }

    public function testUploadFile(): void
    {
        $responseBody = json_encode([
            'url' => 'http://test-url/file.pdf',
            'name' => 'file.pdf',
        ]);
        $this->client->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                'http://test-api-url/files/test.pdf',
                $this->callback(function (array $options) {
                    return $options['headers']['X-Parse-Application-Id'] === 'test-app-id'
                        && $options['body'] === 'dummy-content';
                })
            )
            ->willReturn(new Response(201, [], $responseBody ?: ''));

        $result = $this->service->uploadFile('test.pdf', 'dummy-content', 'application/pdf');

        $this->assertArrayHasKey('url', $result);
        $this->assertEquals('http://test-url/file.pdf', $result['url']);
    }

    public function testCreateSignatureRequest(): void
    {
        $responseBody = json_encode([
            'objectId' => '12345',
        ]);
        $this->client->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                'http://test-api-url/classes/contracts_Document',
                $this->callback(function (array $options) {
                    return $options['headers']['X-Parse-Session-Token'] === 'test-session-token'
                        && isset($options['json']['Name'])
                        && $options['json']['CreatedBy']['objectId'] === 'test-user-id';
                })
            )
            ->willReturn(new Response(201, [], $responseBody ?: ''));

        $payload = [
            'Name' => 'Test Contract',
            'URL' => 'http://test-url/file.pdf',
            'Signers' => [[
                'name' => 'Alice',
            ]],
            'Status' => 'pending',
        ];

        $result = $this->service->createSignatureRequest($payload);

        $this->assertArrayHasKey('objectId', $result);
        $this->assertEquals('12345', $result['objectId']);
    }
}
