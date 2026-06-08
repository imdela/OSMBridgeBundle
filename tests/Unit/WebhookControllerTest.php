<?php

declare(strict_types=1);

namespace Ossm\OssmBridgeBundle\Tests\Unit;

use Ossm\OssmBridgeBundle\Controller\WebhookController;
use Ossm\OssmBridgeBundle\Event\DocumentSignedEvent;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class WebhookControllerTest extends TestCase
{
    private const SECRET = 'test-webhook-secret';

    /**
     * @var EventDispatcherInterface&MockObject
     */
    private EventDispatcherInterface $eventDispatcher;

    private WebhookController $controller;

    protected function setUp(): void
    {
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->controller = new WebhookController($this->eventDispatcher, self::SECRET);
    }

    public function testRejectsRequestWithMissingSignature(): void
    {
        $this->eventDispatcher->expects($this->never())
            ->method('dispatch');

        $request = Request::create('/ossm/webhook', 'POST', [], [], [], [], '{}');
        $response = ($this->controller)($request);

        $this->assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    public function testRejectsRequestWithWrongSecret(): void
    {
        $this->eventDispatcher->expects($this->never())
            ->method('dispatch');

        $request = $this->signedRequest('{"object":{"objectId":"abc","Status":"completed"}}', 'wrong-secret');
        $response = ($this->controller)($request);

        $this->assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    public function testDispatchesEventOnValidSignedCompletedPayload(): void
    {
        $this->eventDispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(DocumentSignedEvent::class), DocumentSignedEvent::NAME);

        $body = json_encode([
            'object' => [
                'objectId' => 'abc',
                'Status' => 'completed',
            ],
        ]);
        $this->assertIsString($body);

        $request = $this->signedRequest($body);
        $response = ($this->controller)($request);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    private function signedRequest(string $body, ?string $secret = null): Request
    {
        $signature = hash_hmac('sha256', $body, $secret ?? self::SECRET);

        return Request::create('/ossm/webhook', 'POST', [], [], [], [], $body)
            ->duplicate(null, null, null, null, null, [
                'HTTP_X_WEBHOOK_SIGNATURE' => $signature,
            ]);
    }
}
