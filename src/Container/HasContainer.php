<?php

declare(strict_types=1);

namespace Projek\Container;

trait HasContainer
{
    /**
     * @var null|ContainerInterface
     */
    protected $container = null;

    /**
     * @see ContainerAware::setContainer()
     */
    public function setContainer(ContainerInterface $container): ContainerAware
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
