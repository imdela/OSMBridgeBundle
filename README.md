<p align="center">
    <img src="https://raw.githubusercontent.com/imdela/OpenSignBridgeBundle/main/media/images/logo.png" alt="OpenSign Bridge" width="200">
</p>

# OpenSignBridgeBundle

> `mosl` stands for **Mosaic OpenSource Library**.

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
    composer require mosl/opensign-bridge-bundle
    ```

2.  Ensure the bundle is registered in `config/bundles.php`:

    ```php
    return [
        // ...
        Mosl\OpenSignBridgeBundle\OpenSignBridgeBundle::class => ['all' => true],
    ];
    ```

3.  Configure your environment variables (`.env`):

    ```env
    OPENSIGN_APP_ID=your_app_id
    OPENSIGN_MASTER_KEY=your_master_key
    OPENSIGN_API_URL=http://your-opensign-url/app
    OPENSIGN_USER_ID=your_system_user_id
    OPENSIGN_SESSION_TOKEN=your_session_token
    ```

    `OPENSIGN_USER_ID`/`OPENSIGN_SESSION_TOKEN` come from `opensignb:opensign:setup`
    (run once against your OpenSign server) — see the [Full Integration Guide](docs/DOCUMENTATION.md).

4.  Add the bundle configuration (`config/packages/opensign_bridge.yaml`):

    ```yaml
    open_sign_bridge:
      opensign:
        app_id: "%env(OPENSIGN_APP_ID)%"
        master_key: "%env(OPENSIGN_MASTER_KEY)%"
        api_url: "%env(OPENSIGN_API_URL)%"
        user_id: "%env(OPENSIGN_USER_ID)%"
        session_token: "%env(OPENSIGN_SESSION_TOKEN)%"
    ```

    The config root is `open_sign_bridge`, not `opensign_bridge` — Symfony derives it
    from the extension class name.

## ⚠️ No Outbound Webhook

The self-hosted, open-source OpenSign server does not call back into your app when a
document is signed — "Live Webhooks" are a paid OpenSign Labs SaaS feature, confirmed
absent from the self-hosted server's own code. Detecting completion means polling via
`OpenSignPollingService`; see the [Full Integration Guide](docs/DOCUMENTATION.md).

## 📖 Documentation

For the full signing flow (required payload fields, polling setup, common pitfalls),
see the [Full Integration Guide](docs/DOCUMENTATION.md).
