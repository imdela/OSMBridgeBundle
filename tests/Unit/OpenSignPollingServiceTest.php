<?php

declare(strict_types=1);

namespace Mosl\OpenSignBridgeBundle\Tests\Unit;

use Mosl\OpenSignBridgeBundle\Contract\PendingDocumentProviderInterface;
use Mosl\OpenSignBridgeBundle\Event\DocumentSignedEvent;
use Mosl\OpenSignBridgeBundle\Service\OpenSignPollingService;
use Mosl\OpenSignBridgeBundle\Service\OpenSignService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class OpenSignPollingServiceTest extends TestCase
{
    /**
     * @var OpenSignService&MockObject
     */
    private OpenSignService $openSignService;

    /**
     * @var EventDispatcherInterface&MockObject
     */
    private EventDispatcherInterface $eventDispatcher;

    protected function setUp(): void
    {
        $this->openSignService = $this->createMock(OpenSignService::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
    }

    public function testNoOpsWithoutProvider(): void
    {
        $service = new OpenSignPollingService($this->openSignService, $this->eventDispatcher, null);

        $this->openSignService->expects($this->never())
            ->method('getDocument');
        $this->eventDispatcher->expects($this->never())
            ->method('dispatch');

        $this->assertSame(0, $service->pollPendingDocuments());
    }

    public function testDispatchesEventForCompletedDocuments(): void
    {
        $provider = $this->createMock(PendingDocumentProviderInterface::class);
        $provider->method('getPendingDocumentIds')
            ->willReturn(['doc-1', 'doc-2']);

        $this->openSignService->method('getDocument')
            ->willReturnMap([
                [
                    'doc-1', [
                        'objectId' => 'doc-1',
                        'IsCompleted' => true,
                    ]],
                [
                    'doc-2', [
                        'objectId' => 'doc-2',
                        'IsCompleted' => false,
                    ]],
            ]);

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with(
                $this->callback(fn (DocumentSignedEvent $event) => $event->getDocumentId() === 'doc-1'),
                DocumentSignedEvent::NAME
            );

        $service = new OpenSignPollingService($this->openSignService, $this->eventDispatcher, $provider);

        $this->assertSame(1, $service->pollPendingDocuments());
    }

    public function testDoesNotDispatchWhenNoneCompleted(): void
    {
        $provider = $this->createMock(PendingDocumentProviderInterface::class);
        $provider->method('getPendingDocumentIds')
            ->willReturn(['doc-1']);

        $this->openSignService->method('getDocument')
            ->willReturn([
                'objectId' => 'doc-1',
                'IsCompleted' => false,
            ]);

        $this->eventDispatcher->expects($this->never())
            ->method('dispatch');

        $service = new OpenSignPollingService($this->openSignService, $this->eventDispatcher, $provider);

        $this->assertSame(0, $service->pollPendingDocuments());
    }
}
