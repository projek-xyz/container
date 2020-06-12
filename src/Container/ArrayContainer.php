<?php

namespace Projek\Container;

final class ArrayContainer extends AbstractContainer implements \ArrayAccess
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
            return $this->container->get($name);
        } catch (NotFoundException $e) {
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
