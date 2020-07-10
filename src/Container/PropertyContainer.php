<?php

declare(strict_types=1);

namespace Projek\Container;

/**
 * This class allow you to access any instance of the container as class property.
 * All you need is register it in the container.
 *
 * ```php
 * $container->set(PropertyContainer::class, PropertyContainer::class);
 * $container->set('db', function () { ... });
 *
 * // so you could have this available anywhere
 * $container->set(SomeInterface::class, function (PropertyContainer $container) {
 *     return new SomeClass($container->db);
 * });
 * ```
 */
final class PropertyContainer extends AbstractContainerAware
{
    /**
     * @param string $name
     * @param mixed $instance
     * @return void
     */
    public function __set(string $name, $instance): void
    {
        $this->container->set($name, $instance);
    }

    /**
     * @param string $name
     * @return mixed
     */
    public function __get(string $name)
    {
        try {
            return $this->getContainer($name);
        } catch (Exception\NotFoundException $e) {
            return null;
        }
    }

    /**
     * @param string $name
     * @return bool
     */
    public function __isset(string $name): bool
    {
        return $this->container->has($name);
    }

    /**
     * @param string $name
     * @return void
     */
    public function __unset(string $name): void
    {
        $this->container->unset($name);
    }
}
