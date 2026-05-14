<?php

declare(strict_types=1);

namespace Projek\Container;

use Psr\Container\ContainerInterface;

/**
 * Interface for services that are container-aware.
 *
 * Classes implementing this interface will have the container instance
 * automatically injected when resolved by the EntryCollector.
 *
 * @package Projek\Container
 */
interface ContainerAware
{
    /**
     * Inject the container instance.
     *
     * @param ContainerInterface $container The container instance.
     * @return static
     */
    public function setContainer(ContainerInterface $container): static;

    /**
     * Retrieve the container or a specific service from it.
     *
     * If no parameter is provided, it returns the `ContainerInterface` instance.
     * If a name is provided, it returns the resolved service from the container.
     *
     * ```php
     * $instance->getContainer(); // Returns Psr\Container\ContainerInterface
     * $instance->getContainer(SomeClass::class); // Returns the SomeClass instance
     * ```
     *
     * @param string|null $name Optional service name to resolve.
     * @return ($name is null ? ContainerInterface : mixed)
     */
    public function getContainer(?string $name = null);
}
