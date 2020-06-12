<?php

namespace Projek\Container;

final class PropertyContainer extends AbstractContainer
{
    /**
     * Register a service as property.
     *
     * ```php
     * $container->foo = function () {
     *     return new Bar();
     * }
     * ```
     *
     * @param string $name
     * @param mixed $instance
     * @return void
     */
    public function __set(string $name, $instance) : void
    {
        $this->container->set($name, $instance);
    }

    /**
     * Register a service as property.
     *
     * ```php
     * $foo = $container->foo;
     * ```
     *
     * @param string $name
     * @return mixed
     */
    public function __get(string $name)
    {
        try {
            return $this->container->get($name);
        } catch (NotFoundException $e) {
            return null;
        }
    }

    /**
     * Register a service as property.
     *
     * ```php
     * if (isset($container->foo)) {
     *     // ...
     * }
     * ```
     *
     * @param string $name
     * @return bool
     */
    public function __isset(string $name) : bool
    {
        return $this->container->has($name);
    }

    /**
     * Register a service as property.
     *
     * ```php
     * unset($container->foo);
     * ```
     *
     * @param string $name
     * @return void
     */
    public function __unset(string $name) : void
    {
        $this->container->unset($name);
    }
}
