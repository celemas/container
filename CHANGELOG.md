# Changelog

## [Unreleased](https://codeberg.org/celemas/container/compare/0.4.0...HEAD)

No notable changes since the last release.

## [0.4.0](https://codeberg.org/celemas/container/src/tag/0.4.0) (2026-05-12)

### Breaking Changes

- Rename package metadata, root namespace, repository URLs, homepage, and author info.

## [0.3.0](https://codeberg.org/celemas/container/src/tag/0.3.0) (2026-04-26)

### Breaking

- `Entry::asIs()` was renamed to `Entry::value()`.
- `Entry::reify()` was removed.
- The root container now seals internal structural mutation after the first `scope()` call.
- Shared entries now resolve in definition-owner context, while scoped and transient entries resolve in requester context.
- Wrapped PSR container fallback now routes through the root container during scoped resolution.

### Added

- Explicit `Entry` lifetimes with `shared()`, `scoped()`, `transient()`, and `lifetime(...)`.
- `Container::scope()` for isolated per-unit-of-work containers.
- `Resettable` and scope-local `Container::reset()` cleanup support.

### Changed

- `Container` now keeps runtime instances in container-local caches instead of on `Entry` objects.
- `Container::definition()` now resolves definitions through parent containers (for example from tags).
- Scope tags now layer over matching root tags and keep scope-local caches.
- Scope reset now clears local entries/caches and resets used resettable services (including scope tags).

## [0.2.0](https://codeberg.org/celemas/container/src/tag/0.2.0) (2026-02-21)

Project renamed from celemas/registry to celemas/container.

Codename: Jonas

### Changed

- Package renamed from `celemas/registry` to `celemas/container`
- Namespace changed from `Celemas\Registry` to `Celemas\Container`
- Main class renamed from `Registry` to `Container`
- Parameter `includeRegistry` renamed to `includeContainer`

## [0.1.0](https://codeberg.org/celemas/container/src/tag/0.1.0) (2026-01-30)

Initial release.

### Added

- PSR-11 compatible dependency injection container
- Service registration and resolution
- Autowiring support via celemas/wire integration
