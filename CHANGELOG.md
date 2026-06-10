# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Security

- Webhook endpoint (`POST /ossm/webhook`) now requires and verifies the
  `x-webhook-signature` header (HMAC-SHA256) against a mandatory `webhook_secret`
  configuration value. Requests with a missing or invalid signature are rejected
  with `401 Unauthorized`. The bundle refuses to boot if `webhook_secret` is not
  configured.
- Updated `guzzlehttp/guzzle`, `guzzlehttp/psr7`, `symfony/yaml`, `symfony/cache`,
  `symfony/http-foundation`, and `symfony/routing` to patched versions, clearing
  all advisories reported by `composer audit`.
- Replaced the abandoned `digimax/dot-env-editor` dev dependency with the actively
  maintained `larament/dot-env-editor`.
- Removed the unused, upstream-discontinued `symfony/proxy-manager-bridge` and
  `friendsofphp/proxy-manager-lts` dependencies.

### Changed

- Removed private, environment-specific paths and repository references from
  `docs/DOCUMENTATION.md` in preparation for public release.

## [0.1.0] - 2026-05-24

Initial standalone extraction and stabilization of the bundle, prior to its first
public release.

### Added

- OpenSign + MinIO integration blueprint and standalone bundle extraction
  (`c4028b7`, `618779d`).
- `OpenSignService`: signature request creation, file upload via MinIO, guest
  signer creation (`2549540`, `23633eb`).
- OpenSign webhook endpoint and `DocumentSignedEvent` (`2549540`).
- `ossmb:install` console command and Symfony Flex recipe manifest for automated
  installation (`1729198`).
- `ossmb:opensign:setup` command to bootstrap an OpenSign instance (admin user,
  tenant, organization, team, schema) (`618779d`).
- Support for Symfony 7 and 8, in addition to 6.4 (`55cef8d`).
- Docker/Taskfile-based local development workflow and documentation
  (`7fd9a63`, `ce912e6`).

### Fixed

- OpenSign `/addadmin` redirect loop by seeding the tenant and configuring the UI
  (`7b05ddc`).
- PHP 8.1 deprecations; bundle can now bootstrap without prior configuration
  (`bc7b0a0`).
- Missing internal `routes.yaml` (`6460be2`).
- Webhook payload parsing and routing issues (`ae3ec8c`).
- Bundle root resolution (`getPath()`) made dynamic instead of hardcoded
  (`96df747`).

### Changed

- Bundle renamed from `OSMBridgeBundle` to `OSSMBridgeBundle` (`9eb6b73`, `b118b85`,
  `b13f15d`).
- OpenSign setup configuration abstracted into modular YAML (`ba4d6cc`).
- Webhook controller structure and payload validation refactored (`f0b905d`).
- Restored PHPStan level max and ECS compliance (`7ef731b`, `3be01fc`).

[Unreleased]: https://github.com/imdela/OSMBridgeBundle/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/imdela/OSMBridgeBundle/releases/tag/v0.1.0
