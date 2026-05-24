<?php

declare(strict_types=1);

namespace Ossm\OssmBridgeBundle\Controller;

use Ossm\OssmBridgeBundle\Event\DocumentSignedEvent;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class WebhookController extends AbstractController
{
    private EventDispatcherInterface $eventDispatcher;

    public function __construct(EventDispatcherInterface $eventDispatcher)
    {
        $this->eventDispatcher = $eventDispatcher;
    }

    public function __invoke(Request $request): JsonResponse
    {
        /** @var array<string, mixed>|null $payload */
        $payload = json_decode($request->getContent(), true) ?: $request->request->all();

        if (!$payload) {
            return new JsonResponse([
                'status' => 'error',
                'message' => 'Invalid JSON payload',
            ], Response::HTTP_BAD_REQUEST);
        }

        // Validate webhook from OpenSign: usually OpenSign sends the Object and its status.
        // E.g., OpenSign might send {"object": {"objectId": "123", "Status": "completed"}}
        $object = $payload['object'] ?? $payload;

        if (is_array($object) && isset($object['objectId'], $object['Status']) && $object['Status'] === 'completed') {
            /** @var array<string, mixed> $object */
            $objectId = $object['objectId'];
            if (is_string($objectId)) {
                $event = new DocumentSignedEvent($objectId, $object);
                $this->eventDispatcher->dispatch($event, DocumentSignedEvent::NAME);
            }
        }

        return new JsonResponse([
            'status' => 'success',
        ]);
    }
}
