<?php

declare(strict_types=1);

namespace Projek\Container;

use Psr\Container\ContainerInterface;

/**
 * Container Aware Interface.
 *
 * Any class implements this interface could have instance of ContainerInterface
 * injected automtically.
 *
 * @package Projek\Container
 */
interface ContainerAware
{
    /**
     * Assign a container to the instance.
     *
     * @param ContainerInterface $container
     * @return static
     */
    public function setContainer(ContainerInterface $container): static;

    /**
     * Retrieve container instance or the instance of registered service.
     *
     * If no parameter given, this method should returns instance of `Projek\Container`
     *
     * ```php
     * $instance->getContainer(); // \Projek\Container
     * ```
     *
     * But if a string given, this method should returns instance of registered
     * Container with the given name
     *
     * ```php
     * $container->set(SomeClass::class, function () { ... });
     *
     * // equivalent to `$container->get(SomeClass::class)`
     * $instance->getContainer(SomeClass::class); // instance of SomeClass
     * ```
     *
     * @param null|string $name Optionally pass a container name, if needed.
     * @return ($name is null ? ContainerInterface : mixed)
     */
    public function getContainer(?string $name = null);
}
