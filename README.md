# Celemas Container

<!-- prettier-ignore-start -->
[![ci](https://codeberg.org/celemas/container/badges/workflows/ci.yml/badge.svg?style=flat&logo=codeberg&logoColor=white&label=ci)](https://codeberg.org/celemas/container/actions)
[![code coverage](https://img.shields.io/endpoint?url=https%3A%2F%2Fcov.celemas.dev%2Fcelemas%2Fcontainer%2Fcode%2Fbadge.json)](https://cov.celemas.dev/celemas/container/code)
[![type coverage](https://img.shields.io/endpoint?url=https%3A%2F%2Fcov.celemas.dev%2Fcelemas%2Fcontainer%2Ftypes%2Fbadge-cover.json)](https://cov.celemas.dev/celemas/container/types)
[![psalm level](https://img.shields.io/endpoint?url=https%3A%2F%2Fcov.celemas.dev%2Fcelemas%2Fcontainer%2Ftypes%2Fbadge-level.json)](https://cov.celemas.dev/celemas/container/types)
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

Use `scope()` to create an isolated container for one unit of work:

```php
$root = new Container();
$root->add('app-name', 'celemas')->value();
$root->add('global-service', GlobalService::class)->shared();
$root->add('request-service', RequestService::class)->scoped();

$scope = $root->scope();
$scope->add(Request::class, $request)->value();

// ... resolve request-local services
$scope->reset();
```

After the first `scope()` call, the root container is sealed and no longer accepts structural mutations. Scope tags can inherit pre-defined root tags while keeping their own local caches.

Services that should be cleaned between scopes can implement `Celemas\Container\Resettable` and will be reset during `$scope->reset()`.

## License

This project is licensed under the [MIT license](LICENSE.md).
