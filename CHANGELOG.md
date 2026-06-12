# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.3.0] - 2026-08-28

### Security

- Webhook endpoint (`POST /ossm/webhook`) now requires and verifies the
  `x-webhook-signature` header (HMAC-SHA256) against a mandatory `webhook_secret`
  configuration value. Requests with a missing or invalid signature are rejected
  with `401 Unauthorized`. The bundle refuses to boot if `webhook_secret` is not
  configured.
- Updated `guzzlehttp/guzzle`, `guzzlehttp/psr7`, `symfony/yaml`, `symfony/cache`,
  `symfony/http-foundation`, and `symfony/routing` to patched versions, clearing
  all advisories reported by `composer audit`.
- Removed the unused, upstream-discontinued `symfony/proxy-manager-bridge` and
  `friendsofphp/proxy-manager-lts` dependencies.
- Pinned the local dev stack's MinIO image to the last officially published,
  still-downloadable release (`RELEASE.2025-09-07T16-13-09Z`) instead of
  floating on `:latest`, and mirrored it publicly at `ghcr.io/imdela/minio`,
  since MinIO's official repository was archived upstream. See
  [KNOWN_ISSUES.md](KNOWN_ISSUES.md).

### Changed

- Removed private, environment-specific paths and repository references from
  `docs/DOCUMENTATION.md` in preparation for public release.
- Replaced a private Docker registry reference for the MongoDB image in
  `compose.yaml` with the public official image.

### Added

- `.github/workflows/ci.yml`: composer audit, ECS, PHPStan, PHPUnit across a
  PHP 8.2/8.3/8.4 x Symfony 6.4/7.4/8.0 matrix.
- `CONTRIBUTING.md`, `SECURITY.md`, `KNOWN_ISSUES.md`.

## [0.2.0] - 2026-05-25

First public release. Fixes a packaging defect present since the bundle's
standalone extraction.

### Fixed

- `digimax/dot-env-editor` was declared `require-dev` but used directly in
  `OpenSignSetupCommand` (a production code path), so `composer install
  --no-dev` broke the `ossmb:opensign:setup` command with a missing class.
  Replaced with the actively maintained `larament/dot-env-editor`.

## [0.1.0] - 2026-05-24

Initial standalone extraction and stabilization of the bundle.

### Added

- OpenSign + MinIO integration blueprint and standalone bundle extraction
  (`d855352`, `263e03d`).
- `OpenSignService`: signature request creation, file upload via MinIO, guest
  signer creation (`c61dbb8`, `6a82fde`).
- OpenSign webhook endpoint and `DocumentSignedEvent` (`c61dbb8`).
- `ossmb:install` console command and Symfony Flex recipe manifest for automated
  installation (`3527651`).
- `ossmb:opensign:setup` command to bootstrap an OpenSign instance (admin user,
  tenant, organization, team, schema) (`263e03d`).
- Support for Symfony 7 and 8, in addition to 6.4 (`bc8100e`).
- Docker/Taskfile-based local development workflow and documentation
  (`8378da6`, `8f479ed`).

### Fixed

- OpenSign `/addadmin` redirect loop by seeding the tenant and configuring the UI
  (`78489a4`).
- PHP 8.1 deprecations; bundle can now bootstrap without prior configuration
  (`0f3fc3d`).
- Missing internal `routes.yaml` (`bcb271e`).
- Webhook payload parsing and routing issues (`eb99ed5`).
- Bundle root resolution (`getPath()`) made dynamic instead of hardcoded
  (`c217419`).

### Changed

- Bundle renamed from `OSMBridgeBundle` to `OSSMBridgeBundle` (`5534817`,
  `e3917e5`, `adb27ec`).
- OpenSign setup configuration abstracted into modular YAML (`47fcb74`).
- Webhook controller structure and payload validation refactored (`e935dc3`).
- Restored PHPStan level max and ECS compliance (`2907574`, `da5c9d1`).

[Unreleased]: https://github.com/imdela/OSMBridgeBundle/compare/v0.3.0...HEAD
[0.3.0]: https://github.com/imdela/OSMBridgeBundle/compare/v0.2.0...v0.3.0
[0.2.0]: https://github.com/imdela/OSMBridgeBundle/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/imdela/OSMBridgeBundle/releases/tag/v0.1.0
