<?php

namespace Projek\Container;

final class ArrayContainer extends AbstractContainer implements \ArrayAccess
{
    /**
     * Register a service as array.
     *
     * ```php
     * $container['foo'] = function () {
     *     return new Bar();
     * }
     * ```
     *
     * @param string $name
     * @param mixed $instance
     * @return void
     */
    public function offsetSet($name, $instance)
    {
        $this->container->set($name, $instance);
    }

    /**
     * Register a service as property.
     *
     * ```php
     * $foo = $container['foo'];
     * ```
     *
     * @param string $name
     * @return mixed
     */
    public function offsetGet($name)
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
     * if (isset($container['foo'])) {
     *     // ...
     * }
     * ```
     *
     * @param string $name
     * @return bool
     */
    public function offsetExists($name)
    {
        return $this->container->has($name);
    }

    /**
     * Unregister a service.
     *
     * ```php
     * unset($container['foo']);
     * ```
     *
     * @param string $name
     * @return void
     */
    public function offsetUnset($name)
    {
        $this->container->unset($name);
    }
}
