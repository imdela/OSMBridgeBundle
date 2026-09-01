# OpenSignBridgeBundle Integration Guide

This guide describes the architecture, configuration, and practical usage required to successfully integrate **OpenSign** with **MinIO** via the `OpenSignBridgeBundle` across any Symfony application.

---

## 🏗️ Architecture Overview

The bundle bridges three main systems communicating in a typical local/cloud network:

1.  **Consumer Symfony App**: The orchestrator requesting signatures (e.g., invoices, timesheets). It triggers the bundle's services to generate records on OpenSign.
2.  **OpenSign Server**: The digital signature platform (Parse Server based). It manages documents, signers, and the legal audit trails.
3.  **MinIO**: An S3-compatible file host to hold the raw physical PDF geometries.

---

## ⚙️ Installation

To install this standalone bundle into your principal business application:

```bash
composer require mosl/opensign-bridge-bundle
```

### ⚡ Automated Setup (Recommended)

The bundle includes an automation utility to save you from manual configuration:

```bash
php bin/console opensignb:install
```

**What this command does:**

- Automatically creates `config/packages/opensign_bridge.yaml`.
- Enabled the webhook route in `config/routes/opensign_bridge.yaml`.
- Appends `OPENSIGN_*` environment placeholders to your `.env` file.
- Copies utility scripts (like the MinIO patch) to your `bin/` directory.

---

### 🛠️ Manual Configuration (Alternative)

If you prefer to configure the bundle manually, follow these steps:

#### 1. Register Bundle

Ensure `config/bundles.php` contains the bridge:

```php
return [
    // ...
    Mosl\OpenSignBridgeBundle\OpenSignBridgeBundle::class => ['all' => true],
];
```

#### 2. Configure Environment

Update your main application's `.env` configuration file:

```env
OPENSIGN_APP_ID=myAppId
OPENSIGN_MASTER_KEY=myMasterKey
OPENSIGN_API_URL=http://opensign:8080/app
OPENSIGN_USER_ID=myAdminUserId
OPENSIGN_SESSION_TOKEN=myPermanentSessionToken
OPENSIGN_WEBHOOK_SECRET=myWebhookSecurityKey
```

`OPENSIGN_WEBHOOK_SECRET` must match the "Webhook Security Key" set in your OpenSign
instance's webhook settings — it is used to verify the `x-webhook-signature` header
(HMAC-SHA256) on every incoming webhook call. It is **required**: the bundle will
refuse to boot if it is missing or empty.

#### 3. Apply the YAML Configuration

Create `config/packages/opensign_bridge.yaml` inside your primary application:

```yaml
opensign_bridge:
  opensign:
    app_id: "%env(OPENSIGN_APP_ID)%"
    master_key: "%env(OPENSIGN_MASTER_KEY)%"
    api_url: "%env(OPENSIGN_API_URL)%"
    user_id: "%env(OPENSIGN_USER_ID)%"
    session_token: "%env(OPENSIGN_SESSION_TOKEN)%"
  webhook_secret: "%env(OPENSIGN_WEBHOOK_SECRET)%"
```

#### 4. Enable the Webhook Route

Inside `config/routes.yaml` of your principal application:

```yaml
opensign_bridge_routes:
  resource: "@OpenSignBridgeBundle/config/routes.yaml"
```

---

## 💻 Developer Usage

The bundle wraps convoluted API orchestration into a single strict `OpenSignService`.

### 1. Generating a Signature Request

To send a signature process from any service in your application, inject `OpenSignService`:

```php
<?php
namespace App\Service;

use Mosl\OpenSignBridgeBundle\Service\OpenSignService;

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

When signers finish standardly via OpenSign, it fires a webhook payload to the registered path (`/opensign/webhook`). Under the hood, the bundle routes this payload natively and dispatches a robust Symfony Event!

Every incoming call is verified against the `x-webhook-signature` header (HMAC-SHA256 of
the raw request body, keyed with `OPENSIGN_WEBHOOK_SECRET`). Requests with a missing or
invalid signature are rejected with `401 Unauthorized` before any event is dispatched.

All your application needs to do is implement a standard `EventSubscriber`:

```php
<?php
namespace App\EventSubscriber;

use Mosl\OpenSignBridgeBundle\Event\DocumentSignedEvent;
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

## 🐳 Docker & Taskfile Tips

If your host application runs inside Docker, use these shortcuts to make your life easier.

### 1. Mapping the Bundle (Local Development)

To work on this bundle and your app at the same time:

```yaml
# In host project's compose.yml
services:
  app:
    volumes:
      - .:/app
      - /path/to/opensign-bridge-bundle:/app/opensign-bridge-bundle
```

### 2. Required Taskfile Definition

Add this to your host project's `Taskfile.yml` to run the bundle tools easily:

```yaml
tasks:
  console:
    cmds:
      - docker compose exec app php bin/console {{.CLI_ARGS}}
```

### 3. Usage Examples

```bash
# Install the bundle's automated config
task console -- opensignb:install

# Bootstrap the OpenSign database
task console -- opensignb:opensign:setup
```
