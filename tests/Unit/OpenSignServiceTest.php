<?php

declare(strict_types=1);

namespace Mosl\OpenSignBridgeBundle\Tests\Unit;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use Mosl\OpenSignBridgeBundle\Service\OpenSignService;
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
                    return self::header($options, 'X-Parse-Application-Id') === 'test-app-id'
                        && is_resource($options['body'])
                        && stream_get_contents($options['body']) === 'dummy-content';
                })
            )
            ->willReturn(new Response(201, [], $responseBody ?: ''));

        $tempFile = tempnam(sys_get_temp_dir(), 'test');
        if ($tempFile !== false) {
            file_put_contents($tempFile, 'dummy-content');
            $result = $this->service->uploadFile('test.pdf', $tempFile, 'application/pdf');
            unlink($tempFile);
        } else {
            $this->fail('Could not create temp file');
        }

        $this->assertArrayHasKey('url', $result);
        $this->assertEquals('http://test-url/file.pdf', $result['url']);
    }

    public function testCreateSignatureRequest(): void
    {
        $this->client->expects($this->exactly(2))
            ->method('request')
            ->willReturnCallback(function (string $method, string $uri, array $options) {
                if ($method === 'GET' && str_contains($uri, '/classes/contracts_Users')) {
                    $responseBody = json_encode([
                        'results' => [[
                            'objectId' => 'profile123',
                        ]],
                    ]);
                    return new Response(200, [], $responseBody ?: '');
                }

                if ($method === 'POST' && str_contains($uri, '/classes/contracts_Document')) {
                    $extUserPtr = self::jsonField($options, 'ExtUserPtr');
                    $createdBy = self::jsonField($options, 'CreatedBy');
                    $this->assertEquals('test-session-token', self::header($options, 'X-Parse-Session-Token'));
                    $this->assertEquals('profile123', is_array($extUserPtr) ? $extUserPtr['objectId'] : null);
                    $this->assertEquals('test-user-id', is_array($createdBy) ? $createdBy['objectId'] : null);
                    $this->assertEquals('Test Contract', self::jsonField($options, 'Name'));

                    $responseBody = json_encode([
                        'objectId' => '12345',
                    ]);
                    return new Response(201, [], $responseBody ?: '');
                }

                throw new \RuntimeException(sprintf('Unexpected request: %s %s', $method, $uri));
            });

        $payload = [
            'Name' => 'Test Contract',
            'URL' => 'http://test-url/file.pdf',
            'Status' => 'pending',
        ];

        $result = $this->service->createSignatureRequest($payload);

        $this->assertArrayHasKey('objectId', $result);
        $this->assertEquals('12345', $result['objectId']);
    }

    public function testProvisionUser(): void
    {
        $responseBody = json_encode([
            'objectId' => 'user123',
            'sessionToken' => 'token123',
        ]);
        $this->client->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                'http://test-api-url/users',
                $this->callback(function (array $options) {
                    return self::header($options, 'X-Parse-Master-Key') === 'test-master-key'
                        && self::jsonField($options, 'username') === 'newuser';
                })
            )
            ->willReturn(new Response(201, [], $responseBody ?: ''));

        $result = $this->service->provisionUser('newuser', 'pass', 'new@example.com', 'New User');

        $this->assertEquals('user123', $result['objectId']);
    }

    public function testGetDocument(): void
    {
        $responseBody = json_encode([
            'objectId' => 'doc123',
            'Name' => 'Test Doc',
        ]);
        $this->client->expects($this->once())
            ->method('request')
            ->with(
                'GET',
                'http://test-api-url/classes/contracts_Document/doc123',
                $this->callback(function (array $options) {
                    return self::header($options, 'X-Parse-Session-Token') === 'test-session-token';
                })
            )
            ->willReturn(new Response(200, [], $responseBody ?: ''));

        $result = $this->service->getDocument('doc123');

        $this->assertEquals('Test Doc', $result['Name']);
    }

    public function testGetExtUserProfileId(): void
    {
        $responseBody = json_encode([
            'results' => [[
                'objectId' => 'profile123',
            ]],
        ]);
        $this->client->expects($this->once())
            ->method('request')
            ->with(
                'GET',
                $this->stringContains('/classes/contracts_Users'),
                $this->callback(function (array $options) {
                    return self::header($options, 'X-Parse-Master-Key') === 'test-master-key';
                })
            )
            ->willReturn(new Response(200, [], $responseBody ?: ''));

        $result = $this->service->getExtUserProfileId();

        $this->assertEquals('profile123', $result);
    }

    public function testCreateGuestSigner(): void
    {
        $this->client->expects($this->exactly(3))
            ->method('request')
            ->willReturnCallback(function (string $method, string $uri, array $options) {
                if ($method === 'POST' && str_contains($uri, '/users')) {
                    $responseBody = json_encode([
                        'objectId' => 'user123',
                    ]);
                    return new Response(201, [], $responseBody ?: '');
                }

                if ($method === 'GET' && str_contains($uri, '/classes/contracts_Contactbook')) {
                    $responseBody = json_encode([
                        'results' => [],
                    ]);
                    return new Response(200, [], $responseBody ?: '');
                }

                if ($method === 'POST' && str_contains($uri, '/classes/contracts_Contactbook')) {
                    $this->assertEquals('guest@example.com', self::jsonField($options, 'Email'));
                    $this->assertEquals('+1234567890', self::jsonField($options, 'Phone'));
                    $responseBody = json_encode([
                        'objectId' => 'contact123',
                    ]);
                    return new Response(201, [], $responseBody ?: '');
                }

                throw new \RuntimeException(sprintf('Unexpected request: %s %s', $method, $uri));
            });

        $result = $this->service->createGuestSigner('guest@example.com', 'Guest User', '+1234567890');
        $this->assertEquals('contact123', $result);
    }

    public function testSignDocument(): void
    {
        $responseBody = json_encode([
            'isCompleted' => true,
            'message' => 'success',
        ]);
        $this->client->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                'http://test-api-url/functions/PDF',
                $this->callback(function (array $options) {
                    return self::header($options, 'X-Parse-Master-Key') === 'test-master-key'
                        && self::jsonField($options, 'docId') === 'doc123'
                        && self::jsonField($options, 'userId') === 'signer123'
                        && self::jsonField($options, 'pdfFile') === 'base64pdf';
                })
            )
            ->willReturn(new Response(200, [], $responseBody ?: ''));

        $result = $this->service->signDocument('doc123', 'signer123', 'base64pdf');

        $this->assertTrue($result['isCompleted']);
    }

    private static function header(mixed $options, string $name): mixed
    {
        $headers = is_array($options) ? $options['headers'] ?? null : null;

        return is_array($headers) ? $headers[$name] ?? null : null;
    }

    private static function jsonField(mixed $options, string $name): mixed
    {
        $json = is_array($options) ? $options['json'] ?? null : null;

        return is_array($json) ? $json[$name] ?? null : null;
    }
}
