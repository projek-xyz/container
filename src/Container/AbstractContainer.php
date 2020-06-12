<?php

namespace Projek\Container;

abstract class AbstractContainer
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * Create new instance.
     *
     * @param ContainerInterface $container
     */
    final public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }
}
