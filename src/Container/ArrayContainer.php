<?php

declare(strict_types=1);

namespace Projek\Container;

/**
 * This class allow you to access any instance of the container as array.
 * All you need is register it in the container.
 *
 * ```php
 * $container->set(ArrayContainer::class, ArrayContainer::class);
 * $container->set('db', function () { ... });
 *
 * // so you could have this available anywhere
 * $container->set(SomeInterface::class, function (ArrayContainer $container) {
 *     return new SomeClass($container['db']);
 * });
 * ```
 *
 * @deprecated Since v0.6
 */
final class ArrayContainer extends AbstractContainerAware implements \ArrayAccess
{
    /**
     * @param string $name
     * @param mixed $instance
     * @return void
     */
    public function offsetSet($name, $instance)
    {
        $this->container->set($name, $instance);
    }

    /**
     * @param string $name
     * @return mixed
     */
    public function offsetGet($name)
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
    public function offsetExists($name)
    {
        return $this->container->has($name);
    }

    /**
     * @param string $name
     * @return void
     */
    public function offsetUnset($name)
    {
        $this->container->unset($name);
    }
}
