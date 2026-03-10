<?php

declare(strict_types=1);

namespace Ossm\OssmBridgeBundle\Controller;

use Ossm\OssmBridgeBundle\Event\DocumentSignedEvent;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class WebhookController
{
    private EventDispatcherInterface $dispatcher;

    public function __construct(EventDispatcherInterface $dispatcher)
    {
        $this->dispatcher = $dispatcher;
    }

    public function __invoke(Request $request): Response
    {
        $content = $request->getContent();
        $payload = json_decode($content, true);

        // Fallback for codeception / form-urlencoded payloads
        if (! is_array($payload) || empty($payload)) {
            $payload = $request->request->all();
        }

        if (empty($payload)) {
            return new Response('Invalid or missing payload', Response::HTTP_BAD_REQUEST);
        }

        // OpenSign typically sends the document under a specific key, like 'document'
        // or just the document ID. Adapt to whatever the real payload structure is.
        // Assuming either an objectId or the document itself includes objectId.
        $documentId = $payload['objectId'] ?? $payload['document']['objectId'] ?? $payload['id'] ?? null;

        if (! $documentId) {
            // Alternatively, in advanced scenarios, maybe the entire payload goes into the event
            // and the receiver handles it. We'll use a placeholder if we can't find it,
            // or we could just pass the payload directly.
            $documentId = 'unknown';
        }

        $event = new DocumentSignedEvent((string) $documentId, $payload);
        $this->dispatcher->dispatch($event, DocumentSignedEvent::NAME);

        return new Response('OK', Response::HTTP_OK);
    }
}
