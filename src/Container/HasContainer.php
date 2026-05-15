<?php

declare(strict_types=1);

namespace Projek\Container;

use Psr\Container\ContainerInterface;

/**
 * Trait providing implementation for the ContainerAware interface.
 *
 * This trait manages the storage and retrieval of the container instance,
 * as well as shorthand service resolution from within the implementing class.
 *
 * @package Projek\Container
 * @see ContainerAware
 */
trait HasContainer
{
    /**
     * @var ContainerInterface|null The injected container instance.
     */
    protected ?ContainerInterface $container = null;

    /**
     * Set the container instance.
     *
     * {@inheritdoc}
     * @see ContainerAware::setContainer()
     * @param ContainerInterface $container The container instance.
     * @return static
     */
    public function setContainer(ContainerInterface $container): static
    {
        $this->container = $container;

        return $this;
    }

    /**
     * Get the container instance or a resolved service.
     *
     * {@inheritdoc}
     * @see ContainerAware::getContainer()
     * @param string|null $name Optional service name to resolve.
     * @return ($name is null ? ContainerInterface : mixed)
     */
    public function getContainer(?string $name = null)
    {
        if ($this->container && $name) {
            return $this->container->get($name);
        }

        return $this->container;
    }
}
