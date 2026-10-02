# Celema Container

<!-- prettier-ignore-start -->
[![ci](https://codefloe.com/celema/container/badges/workflows/ci.yml/badge.svg?style=flat&logo=forgejo&logoColor=white&label=ci)](https://codefloe.com/celema/container/actions)
[![code coverage](https://img.shields.io/endpoint?url=https%3A%2F%2Fcov.celema.dev%2Fcelema%2Fcontainer%2Fcode%2Fbadge.json)](https://cov.celema.dev/celema/container/code)
[![type coverage](https://img.shields.io/endpoint?url=https%3A%2F%2Fcov.celema.dev%2Fcelema%2Fcontainer%2Ftypes%2Fbadge-cover.json)](https://cov.celema.dev/celema/container/types)
[![psalm level](https://img.shields.io/endpoint?url=https%3A%2F%2Fcov.celema.dev%2Fcelema%2Fcontainer%2Ftypes%2Fbadge-level.json)](https://cov.celema.dev/celema/container/types)
[![Software License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE.md)
<!-- prettier-ignore-end -->

A PSR-11 compatible dependency injection container.

## Entry lifetimes

Container entries use explicit lifetimes:

- `shared()` caches one instance per container (default for added entries)
- `scoped()` caches one instance per requesting container scope
- `transient()` never caches
- `value()` returns the configured definition as-is (for literals or raw closures)

```php
$container->add('config', ['debug' => true])->value();
$container->add(Service::class)->shared();
$container->add(RequestContext::class)->scoped();
$container->add(Builder::class)->transient();
```

## Scope mode

Use `scope()` to create an isolated container for one unit of work, such as an HTTP request in a long-running worker or a job in a queue consumer:

```php
$root = new Container();
$root->add('app-name', 'celema')->value();
$root->add('global-service', GlobalService::class)->shared();
$root->add('request-service', RequestService::class)->scoped();

$scope = $root->scope();
$scope->add(Request::class, $request)->value();

// ... resolve request-local services
$scope->reset();
```

Register everything before the first unit of work. The first `scope()` call seals the root: adding entries or tags to it throws afterwards. Reading a tag that was never registered on a sealed root returns an empty, sealed tag container.

Lifetimes across scopes:

- Shared entries of the root live as long as the root, so they must not keep per-scope state. They resolve their dependencies in the root's context.
- Scoped entries get one instance per scope and resolve their dependencies in that scope, so scope-local entries are visible to them.
- A scoped entry cannot be resolved by the sealed root. That usually means a shared entry depends on it, which would keep the first instance for all later scopes; the container throws a `ContainerException` instead. Make the consumer scoped or transient, or resolve the entry from a scope.
- Resolve scoped entries only from a scope. Scoped instances the root created before the first `scope()` call are dropped when it seals, but a shared entry resolved before that call keeps the scoped instances it received, for every later scope; the container cannot detect that.
- A prebuilt object (`$root->add('id', $object)`) is always shared. Register a class name or a closure for a scoped or transient lifetime.

Tags work the same way inside a scope: `$scope->tag('name')` lists the root tag's registrations plus its own, keeps its own scoped instances, and resolves untagged ids through the scope.

### Resetting a scope

Services that should be cleaned up at the end of a scope implement `Celema\Container\Resettable`. `$scope->reset()` calls `reset()` on every resettable instance the scope created or handed out (shared ones included, once each), then clears the scope's instances, local entries and tags, so the scope can be reused. Resetting the root does nothing.

Every reset hook is attempted even if some fail, and the scope is cleared in any case. Failures are reported afterwards as one `Celema\Container\Exception\ResetFailed`, which lists all of them in `$failures` and carries the first as its previous exception.

## License

This project is licensed under the [MIT license](LICENSE.md).
