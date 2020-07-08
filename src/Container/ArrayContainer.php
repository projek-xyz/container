<?php

declare(strict_types=1);

namespace Projek\Container;

final class ArrayContainer extends AbstractContainerAware implements \ArrayAccess
{
    /**
     * @param string $name
     * @param mixed $instance
     * @return void
     */
    public function offsetSet($name, $instance)
    {
        $this->getContainer()->set($name, $instance);
    }

    /**
     * @param string $name
     * @return mixed
     */
    public function offsetGet($name)
    {
        try {
            return $this->getContainer($name);
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
        return $this->getContainer()->has($name);
    }

    /**
     * @param string $name
     * @return void
     */
    public function offsetUnset($name)
    {
        $this->getContainer()->unset($name);
    }
}
