<?php

declare(strict_types=1);

namespace Projek\Container;

use Psr\Container\ContainerInterface;

/**
 * Explicit factory door for producing an entry's instance from the resolving container.
 *
 * Implement this interface — typically on an anonymous class — when you need control over how
 * an entry is produced (registering a pre-built object instance, wiring dependencies manually):
 * `set()` stores the implementing instance as a `FactoryEntry`, captures `create()`'s signature as
 * entry metadata for `extend()`, and invokes `create()` with the resolving container on every build
 * (`get()` caches the result; `make()` produces a fresh one each time).
 *
 * ```php
 * $container->set('logger', new class implements EntryFactory
 * {
 *     public function create(ContainerInterface $container): LoggerInterface
 *     {
 *         $contig = $container->get(ConfigInterface::class);
 *
 *         return new MyLoggerImplementation($contig['logger']);
 *     }
 * });
 * ```
 */
interface EntryFactory
{
    /**
     * Produce the entry's instance from the container it is being built for.
     *
     * @param  ContainerInterface  $container  The resolving container — factories wire their dependencies from it.
     */
    public function create(ContainerInterface $container): object;
}
