<?php

declare(strict_types=1);

namespace Projek;

use Projek\Container\{ContainerInterface, Exception, Resolver};
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
     * List of instances that been handled.
     *
     * @var array<string, mixed>
     */
    private $handledInstances = [];

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
        $this->instances = [
            self::class => $this,
            ContainerInterface::class => $this,
            PsrContainerInterface::class => $this,
        ];

        foreach ($instances as $id => $instance) {
            $this->set($id, $instance);
        }
    }

    /**
     * Create new resolver instance when get cloned.
     */
    public function __clone()
    {
        $this->resolver = new Resolver($this);
    }

    /**
     * {@inheritDoc}
     */
    public function get(string $id)
    {
        if (! $this->has($id)) {
            throw new Exception\NotFoundException($id);
        }

        if (isset($this->handledInstances[$id])) {
            return $this->handledInstances[$id];
        }

        $instance = $this->instances[$id];

        if (\is_object($instance) && ! \is_callable($instance)) {
            return $instance;
        }

        return $this->handledInstances[$id] = $this->resolver->handle($instance);
    }

    /**
     * {@inheritDoc}
     */
    public function has(string $id): bool
    {
        return \array_key_exists($id, $this->instances);
    }

    /**
     * {@inheritDoc}
     */
    public function set(string $id, $instance): ContainerInterface
    {
        if ($this->has($id)) {
            return $this;
        }

        $this->instances[$id] = $this->resolver->resolve($instance);

        if (isset($this->handledInstances[$id])) {
            unset($this->handledInstances[$id]);
        }

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function unset(string $id): void
    {
        unset($this->instances[$id]);

        if (isset($this->handledInstances[$id])) {
            unset($this->handledInstances[$id]);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function make($concrete, ...$args)
    {
        $instance = $this->resolver->resolve($concrete);

        [$args, $condition] = ($count = \count($args = \array_filter($args)))
            ? $this->assertParams($count, $args)
            : [[], null];

        if ($condition instanceof \Closure) {
            $instance = $condition($instance) ?: $instance;
        }

        return $this->resolver->handle($instance, $args);
    }

    /**
     * Assert $argumens and $condition by $params
     *
     * @param int $count
     * @param array $params
     * @return array List of [$argumens, $condition]
     */
    private function assertParams(int $count, array $params = []): array
    {
        if (2 === $count) {
            if (! \is_array($params[0])) {
                throw new Exception\InvalidArgumentException(2, ['array'], $params[0]);
            } elseif (! ($params[1] instanceof \Closure) && null !== $params[1]) {
                throw new Exception\InvalidArgumentException(3, ['Closure'], $params[1]);
            }

            return $params;
        }

        if (1 === $count) {
            if (! \is_array($params[0]) && ! ($params[0] instanceof \Closure)) {
                throw new Exception\InvalidArgumentException(2, ['array', 'Closure'], $params[0]);
            }

            return [
                \is_array($params[0]) ? $params[0] : [],
                $params[0] instanceof \Closure ? $params[0] : null
            ];
        }

        throw new Exception\RangeException(3, $count + 1);
    }
}
