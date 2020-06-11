<?php

namespace Projek\Container;

final class PropertyContainer
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
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * @param string $abstract
     * @param mixed $concrete
     * @return void
     */
    public function __set($abstract, $concrete)
    {
        $this->container->set($abstract, $concrete);
    }

    /**
     * @param string $abstract
     * @return mixed
     */
    public function __get($abstract)
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
    public function __isset($abstract)
    {
        return $this->container->has($abstract);
    }

    /**
     * @param string $abstract
     * @return void
     */
    public function __unset($abstract)
    {
        $this->container->unset($abstract);
    }
}
