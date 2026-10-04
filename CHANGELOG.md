# Changelog

## [Unreleased](https://codefloe.com/celema/container/compare/0.6.0...HEAD)

### Fixed

- Entries with closure arguments now use their factory method from `constructor()`. Previously the closure branch ignored it and called `__construct`.

## [0.6.0](https://codefloe.com/celema/container/src/tag/0.6.0) (2026-10-02)

### Breaking Changes

- Require `celema/wire` 0.8. Entries are built with `Creator::create()`, and `Container` no longer implements the removed `WireContainer` interface; `Container::definition()` is removed (use `entry($id)->definition()`).
- Resolving a scoped entry on the sealed root throws a `ContainerException`. This turns a scoped dependency of a shared entry, which silently kept the first instance for all later scopes, into an error. Scoped instances the root created before the first `scope()` call are dropped when it seals.
- Setting a scoped or transient lifetime on a prebuilt object entry throws, as the object cannot be recreated.
- `reset()` attempts every reset hook and clears the scope even if hooks fail, then throws `Exception\ResetFailed` listing all failures. Previously the first failing hook aborted the reset and left the scope's instances and entries in place.

### Fixed

- Tagged entries resolved through a scope see the scope's entries: a scope's tag container resolves untagged ids through the scope instead of the root, so scope-local overrides and scoped lifetimes apply. Shared tagged entries still resolve in the root's context.
- Reading a tag that was never registered on a sealed root returns an empty, sealed tag container instead of throwing.

## [0.5.1](https://codefloe.com/celema/container/src/tag/0.5.1) (2026-08-05)

### Fixed

- Tag registrations are visible from a scope again. `Container::scope()` creates an empty tag container linked to the root's container for the same tag, but `entries()` and `entry()` read only their own entries — unlike `has()` and `get()` — so everything registered under a tag looked absent inside a scope. Both now follow a same-tag parent. The walk deliberately stops there: a tag container on a non-scope container has the owning container as its parent, and a general parent walk would report every service in the container under the tag.
- `Container::entry()` throws `NotFoundException` for an unknown id instead of returning `null` against its declared `Entry` return type, which surfaced as a `TypeError` at the call site. It now behaves like `definition()`.

## [0.5.0](https://codefloe.com/celema/container/src/tag/0.5.0) (2026-07-18)

### Changed

- Renamed the Composer package to `celema/container` and moved PHP classes from `Celemas\Container` to `Celema\Container`.
- Updated the autowiring integration to `celema/wire:^0.7` and the `Celema\Wire` namespace.

### Removed

- Removed the previous Composer package name and PHP namespaces; consumers must update their dependency and imports.

## [0.4.0](https://codefloe.com/celema/container/src/tag/0.4.0) (2026-05-12)

### Breaking Changes

- Rename package metadata, root namespace, repository URLs, homepage, and author info.

## [0.3.0](https://codefloe.com/celema/container/src/tag/0.3.0) (2026-04-26)

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

## [0.2.0](https://codefloe.com/celema/container/src/tag/0.2.0) (2026-02-21)

Project renamed from celemas/registry to celemas/container.

Codename: Jonas

### Changed

- Package renamed from `celemas/registry` to `celemas/container`
- Namespace changed from `Celemas\Registry` to `Celemas\Container`
- Main class renamed from `Registry` to `Container`
- Parameter `includeRegistry` renamed to `includeContainer`

## [0.1.0](https://codefloe.com/celema/container/src/tag/0.1.0) (2026-01-30)

Initial release.

### Added

- PSR-11 compatible dependency injection container
- Service registration and resolution
- Autowiring support via celemas/wire integration
