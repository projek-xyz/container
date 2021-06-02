<?php

declare(strict_types=1);

namespace Projek\Container;

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
     * @param \Projek\Container $container
     * @return static
     */
    public function setContainer(\Projek\Container $container): ContainerAware;

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
     * @return \Projek\Container|mixed
     */
    public function getContainer(?string $name = null);
}
