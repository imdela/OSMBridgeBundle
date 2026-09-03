# OpenSignBridgeBundle Integration Guide

This guide describes the architecture, configuration, and practical usage required to
integrate a self-hosted **OpenSign** server (Parse Server based) with **MinIO** (or any
S3-compatible storage) via the `OpenSignBridgeBundle`.

Every code sample and every field requirement below was verified against a real, running
`opensign/opensignserver:main` container — not inferred from OpenSign's paid SaaS docs,
which describe a different product surface (see the webhook note below).

---

## 🏗️ Architecture Overview

1.  **Consumer Symfony App**: The orchestrator requesting signatures. It calls the
    bundle's `OpenSignService` to create documents, signers, and signature requests on
    OpenSign, and later to poll for completion.
2.  **OpenSign Server**: The self-hosted digital signature platform (Parse Server
    based). It manages documents, signers, and the audit trail. **This bundle does not
    provide the OpenSign server itself** — deploying it (Docker image
    `opensign/opensignserver:main`, its own MongoDB, and object storage) is the
    consuming app's responsibility, the same way it deploys its own Postgres.
3.  **MinIO / S3**: Object storage for the raw PDF files. OpenSign can be configured
    (`USE_LOCAL=false` + `DO_*` env vars) to reuse a storage bucket the host app
    already runs — no separate object-storage container is required.

---

## ⚙️ Installation

```bash
composer require mosl/opensign-bridge-bundle
```

### Manual Configuration

#### 1. Register the bundle

```php
// config/bundles.php
return [
    // ...
    Mosl\OpenSignBridgeBundle\OpenSignBridgeBundle::class => ['all' => true],
];
```

#### 2. Configure environment variables

```env
OPENSIGN_APP_ID=myAppId
OPENSIGN_MASTER_KEY=myMasterKey
OPENSIGN_API_URL=http://opensign:8080/app
OPENSIGN_USER_ID=myAdminUserId
OPENSIGN_SESSION_TOKEN=myPermanentSessionToken
```

`OPENSIGN_USER_ID`/`OPENSIGN_SESSION_TOKEN` are produced by `opensignb:opensign:setup`
(see below) — they don't exist until the server has been bootstrapped once.

#### 3. Add the bundle configuration

```yaml
# config/packages/opensign_bridge.yaml
open_sign_bridge:
  opensign:
    app_id: "%env(OPENSIGN_APP_ID)%"
    master_key: "%env(OPENSIGN_MASTER_KEY)%"
    api_url: "%env(OPENSIGN_API_URL)%"
    user_id: "%env(OPENSIGN_USER_ID)%"
    session_token: "%env(OPENSIGN_SESSION_TOKEN)%"
```

The config root is `open_sign_bridge`, not `opensign_bridge` — Symfony derives it from
the extension class name (`OpenSignBridgeExtension`).

#### 4. Bootstrap the server

Once the OpenSign server is deployed and reachable at `OPENSIGN_API_URL`:

```bash
php bin/console opensignb:opensign:setup .env
```

Creates the system API user, tenant, org, team, and profile; writes the resulting
`OPENSIGN_USER_ID`/`OPENSIGN_SESSION_TOKEN` into the given env file.

---

## 💻 Signing a Document End-to-End

The order below is the one real path that has been exercised against a live server
(`OpenSignService::uploadFile` → `createGuestSigner` → `createSignatureRequest` →
`signDocument` → poll for completion). Skipping a required field crashes the *server
process itself*, not just the request — see the warnings inline.

```php
<?php

namespace App\Service;

use Mosl\OpenSignBridgeBundle\Service\OpenSignService;

class ContractManagerService
{
    public function __construct(private readonly OpenSignService $openSignService)
    {
    }

    public function sendForSignature(string $pdfPath, string $clientEmail, string $clientName): string
    {
        // 1. Upload the PDF.
        $upload = $this->openSignService->uploadFile('contract.pdf', $pdfPath, 'application/pdf');

        // 2. Create (or reuse, by email) a guest signer.
        $signerId = $this->openSignService->createGuestSigner($clientEmail, $clientName);

        // 3. Create the signature request.
        $response = $this->openSignService->createSignatureRequest([
            'Title' => 'Acquisition Contract',
            // REQUIRED, separate from Title: OpenSign's own completion-certificate
            // generator crashes the whole Parse Server process if this is missing —
            // it is not merely rejected as a bad request.
            'Name' => 'Acquisition Contract',
            'Status' => 'draft',
            'File' => [
                '__type' => 'File',
                'name' => $upload['name'],
                'url' => $upload['url'],
            ],
            'Signers' => [
                [
                    '__type' => 'Pointer',
                    'className' => 'contracts_Contactbook',
                    'objectId' => $signerId,
                ],
            ],
            // REQUIRED for completion to ever be detected: OpenSign counts signed
            // audit entries against Placeholders, not against Signers. One entry
            // per signer, signerObjId matching the Signers[] objectId above.
            'Placeholders' => [
                [
                    'signerObjId' => $signerId,
                    'Role' => 'signer',
                ],
            ],
        ]);

        return $response['objectId']; // Store this — it's the document id for everything below.
    }
}
```

### Applying the signature

`signDocument()` calls OpenSign's own PDF cloud function (`signPdf`), which applies a
real cryptographic signature (`@signpdf/signer-p12`) using the server's configured PFX
certificate:

```php
$result = $openSignService->signDocument($documentId, $signerId, base64_encode($pdfBytes));
// $result === ['status' => 'success', 'data' => '<signed pdf url>']
```

The response is `{"status": "success", "data": <url>}` — **not** an `isCompleted`
field, even though the document itself gains an `IsCompleted` column server-side. To
find out whether a document is actually complete, call `getDocument($documentId)` and
check `$document['IsCompleted']`, or use the polling service below.

The OpenSign server must have `PFX_BASE64`/`PASS_PHRASE` configured (a PKCS#12
certificate, self-signed is fine for dev/test) — without it, `signDocument()` fails.

---

## 🔁 Detecting Completion: Polling, Not Webhooks

**The self-hosted, open-source OpenSign server has no outbound webhook.** "Live
Webhooks" are a paid OpenSign Labs SaaS feature — confirmed absent from both the
running server's cloud code and the public `OpenSignLabs/OpenSign` source. Nothing
will ever call back into your app when a document is signed.

The bundle instead provides `OpenSignPollingService`, tagged `#[AsPeriodicTask]`
(Symfony Scheduler): it asks your app which document ids are pending, checks each via
`getDocument()`, and dispatches `DocumentSignedEvent` for the ones that completed.

### 1. Tell the bundle what to poll

```php
<?php

namespace App\Service;

use Mosl\OpenSignBridgeBundle\Contract\PendingDocumentProviderInterface;

class ContractPendingDocumentProvider implements PendingDocumentProviderInterface
{
    public function getPendingDocumentIds(): iterable
    {
        // Yield the OpenSign document id of every Contract awaiting signature.
        foreach ($this->contractRepository->findPendingSignature() as $contract) {
            if ($contract->getOpenSignDocumentId() !== null) {
                yield $contract->getOpenSignDocumentId();
            }
        }
    }
}
```

```yaml
# config/services.yaml
Mosl\OpenSignBridgeBundle\Contract\PendingDocumentProviderInterface:
  alias: App\Service\ContractPendingDocumentProvider
```

Without this, `OpenSignPollingService::pollPendingDocuments()` is a no-op (returns 0,
dispatches nothing) — not an error.

### 2. React to completion

```php
<?php

namespace App\EventSubscriber;

use Mosl\OpenSignBridgeBundle\Event\DocumentSignedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class DocumentCompletedSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [DocumentSignedEvent::NAME => 'onDocumentSigned'];
    }

    public function onDocumentSigned(DocumentSignedEvent $event): void
    {
        // Find your local entity where openSignDocumentId === $event->getDocumentId(),
        // mark it signed, flush.
    }
}
```

### 3. Run the scheduler

`#[AsPeriodicTask]` requires a `messenger:consume scheduler_default` worker process —
add a transport and run a worker:

```yaml
# config/packages/messenger.yaml
framework:
  messenger:
    transports:
      scheduler_default: 'schedule://default'
```

```bash
php bin/console messenger:consume scheduler_default --sleep=5 --limit=50
```

Or trigger a single poll manually/for debugging:

```bash
php bin/console opensignb:poll-signatures
```

---

## 🐳 Docker & Taskfile Tips

If your host application runs inside Docker:

```yaml
# host app's compose.yaml
services:
  scheduler:
    build: { context: ., dockerfile: Dockerfile }
    entrypoint: php bin/console messenger:consume scheduler_default --sleep=5 --limit=50
    depends_on:
      app:
        condition: service_healthy
```

```bash
# Bootstrap the OpenSign database (once, after the server is up)
task console -- opensignb:opensign:setup .env
```
