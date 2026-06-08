# OSSMBridgeBundle

This Symfony bundle provides the complete infrastructure and service layer needed to integrate **OpenSign** and **MinIO** into any Symfony project.

## 🚀 Quick Start (Development)

To test or develop the bundle independently:

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

## 📦 Installation in a Host App

1.  Require the bundle via Composer:

    ```bash
    composer require ossm/ossm-bridge-bundle
    ```

2.  Ensure the bundle is registered in `config/bundles.php`:

    ```php
    return [
        // ...
        Ossm\OssmBridgeBundle\OssmBridgeBundle::class => ['all' => true],
    ];
    ```

3.  Configure your environment variables (`.env`):

    ```env
    OPENSIGN_APP_ID=your_app_id
    OPENSIGN_MASTER_KEY=your_master_key
    OPENSIGN_API_URL=http://your-opensign-url/app
    OPENSIGN_USER_ID=your_system_user_id
    OPENSIGN_SESSION_TOKEN=your_session_token
    OPENSIGN_WEBHOOK_SECRET=your_opensign_webhook_security_key
    ```

    `OPENSIGN_WEBHOOK_SECRET` is **required** — it must match the "Webhook Security Key"
    configured in your OpenSign instance's webhook settings. The bundle uses it to verify
    the `x-webhook-signature` header (HMAC-SHA256) on every incoming webhook call, and
    **refuses to boot if it is missing or empty**. Requests with a missing or invalid
    signature are rejected with `401 Unauthorized`.

4.  Add the bundle configuration (`config/packages/ossm_bridge.yaml`):

    ```yaml
    ossm_bridge:
      opensign:
        app_id: "%env(OPENSIGN_APP_ID)%"
        master_key: "%env(OPENSIGN_MASTER_KEY)%"
        api_url: "%env(OPENSIGN_API_URL)%"
        user_id: "%env(OPENSIGN_USER_ID)%"
        session_token: "%env(OPENSIGN_SESSION_TOKEN)%"
      webhook_secret: "%env(OPENSIGN_WEBHOOK_SECRET)%"
    ```

5.  Register the Webhook routes (`config/routes.yaml`):

    ```yaml
    ossm_bridge_routes:
      resource: "@OssmBridgeBundle/config/routes.yaml"
    ```

## 📖 Documentation

For full details on usage, services, and handling webhooks, see the [Full Integration Guide](docs/DOCUMENTATION.md).
