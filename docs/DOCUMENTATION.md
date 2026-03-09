# OssmBridgeBundle Integration Guide

This guide describes the architecture, configuration, and practical usage required to successfully integrate **OpenSign** with **MinIO** via the `OssmBridgeBundle` across any Symfony application.

---

## 🏗️ Architecture Overview

The bundle bridges three main systems communicating in a typical local/cloud network:

1.  **Consumer Symfony App**: The orchestrator requesting signatures (e.g., invoices, timesheets). It triggers the bundle's services to generate records on OpenSign.
2.  **OpenSign Server**: The digital signature platform (Parse Server based). It manages documents, signers, and the legal audit trails.
3.  **MinIO**: An S3-compatible file host to hold the raw physical PDF geometries.

### 🔥 OpenSign UI Environment Hot-Patch

OpenSign's frontend is a React Single Page Application (SPA). By default, React bakes environment variables (like `REACT_APP_SERVERURL` and `REACT_APP_APPID`) into the static HTML/JS assets at _build time_. This normally makes deploying a generic OpenSign UI Docker image across multiple environments (Dev, Staging, Prod) impossible without rebuilding the image each time.

To solve this, the bundle includes a custom shell script: `resources/scripts/opensign-ui-entrypoint.sh`.

**What this does:**
When the OpenSign UI container boots, this shell script intercepts the startup. It reads the standard Docker runtime environment variables, writes them directly into a static `build/env.js` file as `window.RUNTIME_ENV = { ... }`, and then allows the container to boot up. The React app is patched to read from `window.RUNTIME_ENV` at runtime instead of relying on build-time variables.

This is the exact script that allows you to dynamically point the pre-compiled OpenSign UI Docker container to your specific backend URL without rebuilding the image!

---

## ⚙️ Installation

To install this standalone bundle into your principal business application:

```bash
composer require ossm/ossm-bridge-bundle
```

### 1. Register Bundle

Ensure `config/bundles.php` contains the bridge:

```php
return [
    // ...
    Ossm\OssmBridgeBundle\OssmBridgeBundle::class => ['all' => true],
];
```

### 2. Configure Environment

Update your main application's `.env` configuration file. These tokens are generated during the OpenSign setup (e.g., by running `task opensign:setup` inside the bundle environment):

```env
OPENSIGN_APP_ID=myAppId
OPENSIGN_MASTER_KEY=myMasterKey
OPENSIGN_API_URL=http://opensign:3000/app
OPENSIGN_USER_ID=myAdminUserId
OPENSIGN_SESSION_TOKEN=myPermanentSessionToken
```

### 3. Apply the YAML Configuration

Create `config/packages/ossm_bridge.yaml` inside your primary application:

```yaml
ossm_bridge:
  opensign:
    app_id: "%env(OPENSIGN_APP_ID)%"
    master_key: "%env(OPENSIGN_MASTER_KEY)%"
    api_url: "%env(OPENSIGN_API_URL)%"
    user_id: "%env(string:OPENSIGN_USER_ID)%"
    session_token: "%env(string:OPENSIGN_SESSION_TOKEN)%"
```

### 4. Enable the Webhook Route

Inside `config/routes.yaml` of your principal application:

```yaml
ossm_bridge_routes:
  resource: "@OssmBridgeBundle/config/routes.yaml"
```

---

## 💻 Developer Usage

The bundle wraps convoluted API orchestration into a single strict `OpenSignService`.

### 1. Generating a Signature Request

To send a signature process from any service in your application, inject `OpenSignService`:

```php
<?php
namespace App\Service;

use Ossm\OssmBridgeBundle\Service\OpenSignService;

class ContractManagerService
{
    private OpenSignService $openSignService;

    public function __construct(OpenSignService $openSignService)
    {
        $this->openSignService = $openSignService;
    }

    public function sendForSignature(string $pdfPath, string $clientEmail, string $clientName): void
    {
        // 1. Upload raw bytes to OpenSign (via MinIO)
        $upload = $this->openSignService->uploadFile('contract.pdf', $pdfPath, 'application/pdf');

        // 2. Automate Guest Contact Book Signer
        $signerId = $this->openSignService->createGuestSigner($clientEmail, $clientName);

        // 3. Dispatch Signature Payload
        $response = $this->openSignService->createSignatureRequest([
            'Title' => 'Acquisition Contract',
            'File' => [
                '__type' => 'File',
                'name' => $upload['name'],
                'url' => $upload['url'],
            ],
            'Signers' => [
                [
                    'Role' => 'Signer',
                    'Contact' => [
                        '__type' => 'Pointer',
                        'className' => 'contracts_Contactbook',
                        'objectId' => $signerId
                    ]
                ]
            ],
            // 'Status' => 'draft' or omitted depending on publishing preferences
        ]);

        // $response['objectId'] is the unique signature tracking ID. Store this!
    }
}
```

### 2. Handling Completion via Webhooks

When signers finish standardly via OpenSign, it fires a webhook payload to the registered path (`/ossm/webhook`). Under the hood, the bundle routes this payload natively and dispatches a robust Symfony Event!

All your application needs to do is implement a standard `EventSubscriber`:

```php
<?php
namespace App\EventSubscriber;

use Ossm\OssmBridgeBundle\Event\DocumentSignedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class DocumentCompletedSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        // Intercept native bundle completion dispatch
        return [
            DocumentSignedEvent::NAME => 'onDocumentSigned'
        ];
    }

    public function onDocumentSigned(DocumentSignedEvent $event): void
    {
        $openSignObjectId = $event->getDocumentId();
        $payload = $event->getPayload();

        // Custom business logic:
        // -> Find local Contract entity where signatureId = $openSignObjectId
        // -> $contract->setStatus('VALIDATED');
        // -> $entityManager->flush();
    }
}
```

---

## 🛑 Troubleshooting

- **Container Boot Error**: If Symfony dies on compilation asserting `$appId is required`, it means your primary `.env` or `config/packages/ossm_bridge.yaml` is misconfigured. Ensure variables exist.
- **Webhooks Ignored**: Ensure your main application's firewall allows unauthenticated `POST` payloads from OpenSign domains to route `/ossm/webhook` natively.
