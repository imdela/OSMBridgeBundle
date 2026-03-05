# User Story: OpenSign & MinIO Integration

## Context

As a developer, I want a streamlined way to integrate digital signature capabilities into our Symfony-based services using OpenSign and MinIO, so that we can automate document signing workflows without manual infrastructure overhead.

## Acceptance Criteria

1.  **Infrastructure**: Docker configuration for OpenSign and MinIO is provided and functional.
2.  **Storage**: OpenSign must store documents in MinIO (S3 compatibility) using path-style access.
3.  **Automation**: A Symfony command must exist to bootstrap the OpenSign environment (User, Token, Schema).
4.  **Integration**: A dedicated service (`OpenSignService`) handles file uploads and signature requests via the Parse REST API.
5.  **Documentation**: A full setup guide is provided for developers.

## Future Evolution (Package)

This configuration should be extracted into a reusable Symfony Bundle that:

- Automatically registers the `OpenSignService`.
- Provides configuration parameters for API credentials.
- Includes the setup command.
- Handles webhook events from OpenSign to update local document status.
