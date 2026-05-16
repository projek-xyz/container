[![Version](https://img.shields.io/packagist/v/projek-xyz/container?style=flat-square)](https://packagist.org/packages/projek-xyz/container)
[![Lisence](https://img.shields.io/github/license/projek-xyz/container?style=flat-square)](https://github.com/projek-xyz/container/blob/main/LICENSE)
[![Actions Status](https://img.shields.io/github/actions/workflow/status/projek-xyz/container/test.yml?branch=main&style=flat-square)](https://github.com/projek-xyz/container/actions)
[![Coverage Status](https://img.shields.io/coveralls/github/projek-xyz/container/main?style=flat-square&logo=coveralls)](https://coveralls.io/github/projek-xyz/container)
[![SymfonyInsight Grade](https://img.shields.io/symfony/i/grade/d611f9c0-e2c9-4850-8831-4e55e8e04d94?style=flat-square&logo=symfony)](https://insight.symfony.com/projects/d611f9c0-e2c9-4850-8831-4e55e8e04d94)

# Simple yet Flexible PSR-11 Container Implementation

This tiny library aims to provide a dead-simple PSR-11 implementation with flexible service registration.

## Features

- **PSR-11 Compliant**: Fully implements the PSR-11 `ContainerInterface`.
- **Autowiring**: Automatically resolves dependencies for constructors and callables using type-hints or parameter names.
- **Flexible Registration**: Register services using closures, class names, instances, or even class-method pairs.
- **Service Extension**: Easily modify or wrap existing services using the `extend()` method.
- **On-the-fly Resolution**: Create instances without adding them to the container stack using `make()`.
- **PSR-14 Event Lifecycle**: Fully supports PSR-14 event dispatching for intercepting and filtering container operations.
- **Container Awareness**: Automatically inject the container into services that implement `ContainerAware`.
- **Lightweight**: Minimal dependencies (only PSR-11 and PSR-14 interfaces) and a small footprint.

## Installation

Use [Composer](https://getcomposer.org/)

```bash
$ composer require projek-xyz/container --prefer-dist
```

## Documentation

Documentations and usages available on [wiki](https://github.com/projek-xyz/container/wiki)

## License

This library is open-sourced software licensed under [MIT license](LICENSE.md).
