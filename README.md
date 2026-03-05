# OpenSign & MinIO Integration Bundle Prototype

This branch contains the full infrastructure and service layer needed to integrate **OpenSign** and **MinIO** into a Symfony project.

## 🚀 Quick Start

1.  **Ensure Containers are Up**:

    ```bash
    task up
    ```

2.  **Bootstrap OpenSign**:
    This command creates the API user, generates tokens, and initializes the MongoDB schema.

    ```bash
    task opensign:setup
    ```

3.  **Restart Containers**:
    Apply the new environment variables from `.env.dist` to the running PHP container.

    ```bash
    task restart
    ```

4.  **Verify**:
    Once configured, you can use the `OpenSignService` to trigger signature requests. See the usage example below.

## 📂 Key Components

- **Infrastructure**: `compose.yaml` and `resources/scripts/opensign-minio-patch.js` (S3 Patch).
- **Services**: `App\Service\OpenSignService` (API Client).
- **Commands**: `App\Command\OpenSignSetupCommand` (Automation).
- **Docs**: [Full Integration Guide](DOCUMENTATION.md).

## 💡 Usage Example

```php
// In your service
$upload = $openSignService->uploadFile('contract.pdf', $content, 'application/pdf');
$openSignService->createSignatureRequest([
    'Name' => 'Contract #123',
    'URL' => $upload['url'],
    'Signers' => [['name' => 'Alice', 'email' => 'alice@example.com']]
]);
```
