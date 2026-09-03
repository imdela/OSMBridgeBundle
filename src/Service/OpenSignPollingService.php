<?php

declare(strict_types=1);

namespace Mosl\OpenSignBridgeBundle\Service;

use Mosl\OpenSignBridgeBundle\Contract\PendingDocumentProviderInterface;
use Mosl\OpenSignBridgeBundle\Event\DocumentSignedEvent;
use Symfony\Component\Scheduler\Attribute\AsPeriodicTask;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Periodically checks pending documents against OpenSign and dispatches
 * DocumentSignedEvent for the ones that have completed — the self-hosted
 * OpenSign server has no outbound webhook, so this is the only completion
 * signal available. No-ops if the consuming app never registered a
 * PendingDocumentProviderInterface implementation.
 */
class OpenSignPollingService
{
    public function __construct(
        private readonly OpenSignService $openSignService,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ?PendingDocumentProviderInterface $pendingDocumentProvider = null
    ) {
    }

    /**
     * @return int Number of documents found completed in this run.
     */
    #[AsPeriodicTask(frequency: '5 minutes')]
    public function pollPendingDocuments(): int
    {
        if (! $this->pendingDocumentProvider instanceof PendingDocumentProviderInterface) {
            return 0;
        }

        $completedCount = 0;

        foreach ($this->pendingDocumentProvider->getPendingDocumentIds() as $documentId) {
            $document = $this->openSignService->getDocument($documentId);
            if (($document['IsCompleted'] ?? false) !== true) {
                continue;
            }

            $event = new DocumentSignedEvent($documentId, $document);
            $this->eventDispatcher->dispatch($event, DocumentSignedEvent::NAME);
            ++$completedCount;
        }

        return $completedCount;
    }
}
