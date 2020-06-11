<?php

namespace Projek\Container;

final class ArrayContainer implements \ArrayAccess
{
    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * Create new instance.
     *
     * @param ContainerInterface $container
     */
    final public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * @param string $abstract
     * @param mixed $concrete
     * @return void
     */
    public function offsetSet($abstract, $concrete)
    {
        $this->container->set($abstract, $concrete);
    }

    /**
     * @param string $abstract
     * @return mixed
     */
    public function offsetGet($abstract)
    {
        try {
            return $this->container->get($abstract);
        } catch (NotFoundException $e) {
            return null;
        }
    }

    /**
     * @param string $abstract
     * @return bool
     */
    public function offsetExists($abstract)
    {
        return $this->container->has($abstract);
    }

    /**
     * @param string $abstract
     * @return void
     */
    public function offsetUnset($abstract)
    {
        $this->container->unset($abstract);
    }
}
