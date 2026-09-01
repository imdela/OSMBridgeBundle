<?php

declare(strict_types=1);

namespace Mosl\OpenSignBridgeBundle\Controller;

use Mosl\OpenSignBridgeBundle\Event\DocumentSignedEvent;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class WebhookController extends AbstractController
{
    private EventDispatcherInterface $eventDispatcher;

    private string $webhookSecret;

    public function __construct(EventDispatcherInterface $eventDispatcher, string $webhookSecret)
    {
        $this->eventDispatcher = $eventDispatcher;
        $this->webhookSecret = $webhookSecret;
    }

    public function __invoke(Request $request): JsonResponse
    {
        $rawBody = $request->getContent();
        $receivedSignature = $request->headers->get('x-webhook-signature');
        $expectedSignature = hash_hmac('sha256', $rawBody, $this->webhookSecret);

        if (! is_string($receivedSignature) || ! hash_equals($expectedSignature, $receivedSignature)) {
            return new JsonResponse([
                'status' => 'error',
                'message' => 'Invalid or missing webhook signature',
            ], Response::HTTP_UNAUTHORIZED);
        }

        /** @var array<string, mixed>|null $payload */
        $payload = json_decode($rawBody, true) ?: $request->request->all();

        if (! $payload) {
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
