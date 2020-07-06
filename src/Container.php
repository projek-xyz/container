<?php

declare(strict_types=1);

namespace Projek;

use Projek\Container\{ContainerInterface, InvalidArgumentException, NotFoundException, RangeException, Resolver};
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
     * List of instances that been resolved.
     *
     * @var array<string, mixed>
     */
    private $resolved = [];

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
     * Create new resolver instance when get cloned.
     */
    public function __clone()
    {
        $this->resolver = new Resolver($this);
    }

    /**
     * {@inheritDoc}
     */
    public function get($id)
    {
        if (! $this->has($id)) {
            throw new NotFoundException($id);
        }

        if (isset($this->resolved[$id])) {
            return $this->resolved[$id];
        }

        $instance = $this->instances[$id];

        if (is_callable($instance)) {
            return $this->resolved[$id] = $this->resolver->handle($instance);
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
    public function make($concrete, ...$args)
    {
        $instance = $this->resolver->resolve($concrete);

        list($args, $condition) = ($count = count($args = array_filter($args)))
            ? $this->assertParams($count, $args)
            : [[], null];

        if (null !== $condition) {
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
            if (! is_array($params[0])) {
                throw new InvalidArgumentException(2, ['array'], gettype($params[0]));
            } elseif (! ($params[1] instanceof \Closure) && null !== $params[1]) {
                throw new InvalidArgumentException(3, ['Closure'], gettype($params[1]));
            }

            return [$params[0], $params[1]];
        }

        if (1 === $count) {
            if (! is_array($params[0]) && ! ($params[0] instanceof \Closure)) {
                throw new InvalidArgumentException(2, ['array', 'Closure'], gettype($params[0]));
            }

            $arguments = is_array($params[0]) ? $params[0] : [];
            $condition = $params[0] instanceof \Closure ? $params[0] : null;

            return [$arguments, $condition];
        }

        throw new RangeException(3, $count + 1);
    }
}
