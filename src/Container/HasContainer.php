<?php

declare(strict_types=1);

namespace Projek\Container;

use Psr\Container\ContainerInterface;

/**
 * Trait providing the `ContainerAware` implementation: it stores the injected container and offers
 * shorthand service resolution from within the implementing class.
 *
 * @see ContainerAware
 */
trait HasContainer
{
    /**
     * @var ContainerInterface|null The injected container instance.
     */
    protected ?ContainerInterface $container = null;

    /**
     * Store the container instance; returns `$this` for fluent chaining.
     *
     * {@inheritdoc}
     *
     * @see ContainerAware::setContainer()
     *
     * @param  ContainerInterface  $container  The container instance.
     */
    public function setContainer(ContainerInterface $container): static
    {
        $this->container = $container;

        return $this;
    }

    /**
     * Get the container instance or a resolved service.
     *
     * Without a `$name` this returns the injected `ContainerInterface`; with a `$name` the service
     * is resolved through `ContainerInterface::get()` (`null` until injection happened).
     *
     * {@inheritdoc}
     *
     * @see ContainerAware::getContainer()
     *
     * @param  string|null  $name  Optional service name to resolve.
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
