# Known Issues

Tracks known limitations, unresolved CVEs, and planned work for
`ossm/ossm-bridge-bundle`. Updated whenever a related fix ships — see
[CHANGELOG.md](CHANGELOG.md) for what has already been released.

## Infrastructure dependencies

### MinIO (local dev/test stack only — not a runtime dependency)

- **Status**: The official [minio/minio](https://github.com/minio/minio)
  repository stopped publishing free Docker images on 2025-10-15 and was
  archived upstream on 2026-04-25 ("no longer maintained"). The vendor is
  redirecting development to a commercial product (AIStor).
- **Impact on this bundle**: None at the PHP/Composer level — the bundle has
  no direct dependency on MinIO. MinIO is only used as the local Docker
  Compose S3-compatible backend for OpenSign in `compose.yaml`, for local
  development and integration testing.
- **What we did**: `compose.yaml` now pins the last officially published,
  still-downloadable release tag
  (`RELEASE.2025-09-07T16-13-09Z`) instead of floating on `:latest`, and
  mirrors that exact image publicly at `ghcr.io/imdela/minio` so local setup
  does not depend on `quay.io/minio/minio` staying reachable indefinitely.
- **Planned**: Migrate the local dev stack to an actively maintained
  community-driven successor once one stabilizes as the de facto standard
  (candidates as of this writing: the
  [pgsty/minio](https://hub.docker.com/r/pgsty/minio) community fork, or
  [Garage](https://garagehq.deuxfleurs.fr/) as a non-fork S3-compatible
  replacement). Tracked here until resolved.

## Dependencies

- No open `composer audit` advisories as of the last dependency update (see
  CHANGELOG `[Unreleased]`). Re-checked on every CI run.

## Known limitations

- The README installation path has been reviewed but not yet validated by
  installing the bundle from scratch into a fresh host application.

---

When a listed item is resolved, move its entry to `CHANGELOG.md` under the
appropriate release and remove it from this file.
