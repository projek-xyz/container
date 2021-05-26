<?php

declare(strict_types=1);

namespace Projek\Container;

/**
 * A trait to help injecting ContainerInterface to the class that implements
 * ContainerAware interface.
 */
trait HasContainer
{
    /**
     * @var \Projek\Container|null
     */
    protected $container = null;

    /**
     * @see ContainerAware::setContainer()
     */
    public function setContainer(\Projek\Container $container): ContainerAware
    {
        $this->container = $container;

        return $this;
    }

    /**
     * @see ContainerAware::getContainer()
     */
    public function getContainer(?string $name = null)
    {
        if ($this->container && $name) {
            return $this->container->get($name);
        }

        return $this->container;
    }
}
