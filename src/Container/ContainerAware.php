<?php

declare(strict_types=1);

namespace Projek\Container;

trait ContainerAware
{
    /**
     * @var null|ContainerInterface
     */
    protected $container = null;

    /**
     * @see ContainerAwareInterface::setContainer()
     */
    public function setContainer(ContainerInterface $container): void
    {
        $this->container = $container;
    }

    /**
     * @see ContainerAwareInterface::getContainer()
     */
    public function getContainer(?string $name = null)
    {
        if ($this->container && $name) {
            return $this->container->get($name);
        }

        return $this->container;
    }
}
