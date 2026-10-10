<?php

declare(strict_types=1);

namespace Projek\Container;

use Psr\Container\ContainerInterface;

/**
 * Interface for services that are container-aware.
 *
 * Classes implementing this interface receive the container instance automatically when they are
 * resolved: `Container::get()` injects through the `EntryResolved` listener (the built-in
 * `Events\ListenerProvider`), while `Container::make()` injects directly without dispatching
 * events. Injection only happens while `getContainer()` still returns `null` — an already-set
 * container is never overwritten, and a custom event dispatcher without the built-in
 * `ListenerProvider` wired in silently disables the `get()` path.
 *
 * @see HasContainer
 */
interface ContainerAware
{
    /**
     * Inject the container instance.
     *
     * @param  ContainerInterface  $container  The container instance.
     */
    public function setContainer(ContainerInterface $container): static;

    /**
     * Retrieve the container or a specific service from it.
     *
     * Without a `$name` it returns the injected `ContainerInterface`; with a `$name` it resolves
     * that service from the container (`null` until a container has been injected).
     *
     * ```php
     * $instance->getContainer(); // Returns Psr\Container\ContainerInterface
     * $instance->getContainer(SomeClass::class); // Returns the SomeClass instance
     * ```
     *
     * @param  string|null  $name  Optional service name to resolve.
     * @return ($name is null ? ContainerInterface : mixed)
     */
    public function getContainer(?string $name = null);
}
