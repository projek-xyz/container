<?php

declare(strict_types=1);

namespace Projek\Container;

/**
 * Container Aware Interface.
 *
 * Any class implements this interface could have instance of ContainerInterface
 * injected automtically.
 *
 * @see Resolver::injectContainer()
 */
interface ContainerAware
{
    /**
     * Assign a container to the instance.
     *
     * @param ContainerInterface $container
     * @return void
     */
    public function setContainer(ContainerInterface $container): void;

    /**
     * Retrieve container instance or the instance of registered service.
     *
     * If no parameter given, this method should returns instance of `ContainerInterface`
     *
     * ```php
     * $instance->getContainer(); // ContainerInterface
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
     * @return null|mixed|ContainerInterface
     */
    public function getContainer(?string $name = null);
}
