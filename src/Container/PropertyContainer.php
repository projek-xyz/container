<?php

namespace Projek\Container;

final class PropertyContainer extends AbstractContainer
{
    /**
     * @param string $name
     * @param mixed $instance
     * @return void
     */
    public function __set(string $name, $instance) : void
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
            return $this->container->get($name);
        } catch (NotFoundException $e) {
            return null;
        }
    }

    /**
     * @param string $name
     * @return bool
     */
    public function __isset(string $name) : bool
    {
        return $this->container->has($name);
    }

    /**
     * @param string $name
     * @return void
     */
    public function __unset(string $name) : void
    {
        $this->container->unset($name);
    }
}
