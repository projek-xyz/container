<?php

declare(strict_types=1);

namespace Projek\Container;

trait ContainerAware
{
    /**
     * @var ContainerInterface
     */
    private $container = null;

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
        if ($name) {
            return $this->container->get($name);
        }

        return $this->container;
    }
}
