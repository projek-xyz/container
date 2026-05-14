<?php

declare(strict_types=1);

namespace Projek\Container;

use Psr\Container\ContainerInterface;

/**
 * A trait to help injecting ContainerInterface to the class that implements
 * ContainerAware interface.
 *
 * @package Projek\Container
 */
trait HasContainer
{
    /**
     * @var ContainerInterface|null
     */
    protected ?ContainerInterface $container = null;

    /**
    * {@inheritdoc}
     * @see ContainerAware::setContainer()
     */
    public function setContainer(ContainerInterface $container): static
    {
        $this->container = $container;

        return $this;
    }

    /**
     * {@inheritdoc}
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
