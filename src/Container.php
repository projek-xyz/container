<?php

declare(strict_types=1);

namespace Projek;

use Projek\Container\ContainerInterface;
use Projek\Container\NotFoundException;
use Projek\Container\Resolver;
use Psr\Container\ContainerInterface as PsrContainerInterface;

class Container implements ContainerInterface
{
    /**
     * List of instances that been initiated.
     *
     * @var array<string, mixed>
     */
    private $instances = [];

    /**
     * Service container resolver.
     *
     * @var Resolver
     */
    private $resolver;

    /**
     * Create new instance.
     *
     * @param array<string, mixed> $instances
     */
    public function __construct(array $instances = [])
    {
        $this->resolver = new Resolver($this);

        $this->set(self::class, $this);
        $this->set(ContainerInterface::class, self::class);
        $this->set(PsrContainerInterface::class, self::class);

        foreach ($instances as $id => $instance) {
            $this->set($id, $instance);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function get($id)
    {
        if (! $this->has($id)) {
            throw new NotFoundException($id);
        }

        $instance = $this->instances[$id];

        if (is_callable($instance)) {
            return $this->resolver->handle($instance);
        }

        if (is_string($instance) && $this->has($instance)) {
            return $this->get($instance);
        }

        return $instance;
    }

    /**
     * {@inheritDoc}
     */
    public function has($id)
    {
        return array_key_exists($id, $this->instances);
    }

    /**
     * {@inheritDoc}
     */
    public function set(string $id, $instance): void
    {
        if ($this->has($id)) {
            return;
        }

        $this->instances[$id] = $this->resolver->resolve($instance);
    }

    /**
     * {@inheritDoc}
     */
    public function unset(string $id): void
    {
        unset($this->instances[$id]);
    }

    /**
     * {@inheritDoc}
     */
    public function make(string $concrete, ?\Closure $condition = null)
    {
        $instance = $this->resolver->resolve($concrete);

        if (null !== $condition) {
            $instance = $condition($instance) ?? $instance;
        }

        return $this->resolver->handle($instance);
    }
}
