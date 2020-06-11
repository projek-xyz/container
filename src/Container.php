<?php

namespace Projek;

use Projek\Container\ContainerInterface;
use Projek\Container\NotFoundException;
use Projek\Container\Resolver;
use Psr\Container\ContainerInterface as PsrContainerInterface;

class Container implements ContainerInterface
{
    /**
     * @var array List of instances that been initiated.
     */
    private $instances = [];

    /**
     * @var array List of resolved instances.
     */
    private $resolved;

    /**
     * @var Resolver Service container resolver.
     */
    private $resolver;

    /**
     * Create new instance.
     *
     * @param array $instances
     */
    public function __construct(array $instances = [])
    {
        $this->resolver = new Resolver($this);

        $this->set(ContainerInterface::class, $this);
        $this->set(PsrContainerInterface::class, $this);

        foreach ($instances as $abstract => $concrete) {
            $this->set($abstract, $concrete);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function get($abstract)
    {
        if (! $this->has($abstract)) {
            throw new NotFoundException($abstract);
        }

        if (isset($this->resolved[$abstract])) {
            return $this->resolved[$abstract];
        }

        $instance = $this->instances[$abstract];

        if (is_callable($instance)) {
            $this->resolved[$abstract] = $this->resolver->handle($instance);
            return $this->resolved[$abstract];
        }

        return $instance;
    }

    /**
     * {@inheritDoc}
     */
    public function has($abstract) : bool
    {
        return array_key_exists($abstract, $this->instances);
    }

    /**
     * {@inheritDoc}
     */
    public function set(string $abstract, $concrete) : void
    {
        if ($this->has($abstract)) {
            return;
        }

        $this->instances[$abstract] = $this->resolver->resolve($concrete);
    }

    /**
     * {@inheritDoc}
     */
    public function unset(string $abstract) : void
    {
        unset($this->instances[$abstract]);
    }
}
