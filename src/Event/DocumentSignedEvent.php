<?php

declare(strict_types=1);

namespace Ossm\OssmBridgeBundle\Event;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched when OpenSign sends a webhook indicating a document's status has changed (e.g., signed/completed).
 */
class DocumentSignedEvent extends Event
{
    public const NAME = 'ossm_bridge.document.signed';

    private string $documentId;

    /**
     * @var array<string, mixed>
     */
    private array $payload;

    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(string $documentId, array $payload)
    {
        $this->documentId = $documentId;
        $this->payload = $payload;
    }

    public function getDocumentId(): string
    {
        return $this->documentId;
    }

    /**
     * @return array<string, mixed>
     */
    public function getPayload(): array
    {
        return $this->payload;
    }
}
