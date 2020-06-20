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
    public function make(string $concrete, ...$params)
    {
        $instance = $this->resolver->resolve($concrete);

        list($args, $condition) = $this->assertParams($params);

        if (null !== $condition) {
            $instance = $condition($instance) ?? $instance;
        }

        return $this->resolver->handle($instance, $args);
    }

    /**
     * Assert $argumens and $condition by $params
     *
     * @param array $params
     * @return array List of [$argumens, $condition]
     */
    private function assertParams(array $params = []): array
    {
        $arguments = [];
        $condition = null;

        switch (count($params)) {
            case 0:
                // do nothing
                break;
            case 1:
                $arguments = is_array($params[0]) ? $params[0] : [];
                $condition = $params[0] instanceof \Closure ? $params[0] : null;

                if (! is_array($params[0]) && ! ($params[0] instanceof \Closure)) {
                    throw new \InvalidArgumentException(
                        sprintf('Expect parameter 2 to be an array or Closure, %s given', gettype($params[0]))
                    );
                }
                break;
            case 2:
                if (! is_array($params[0])) {
                    throw new \InvalidArgumentException(
                        sprintf('Expect parameter 2 to be an array or Closure, %s given', gettype($params[0]))
                    );
                }
                if (! ($params[1] instanceof \Closure) && null !== $params[1]) {
                    throw new \InvalidArgumentException(
                        sprintf('Expect parameter 3 to be a Closure, %s given', gettype($params[0]))
                    );
                }

                $arguments = $params[0];
                $condition = $params[1];
                break;
            default:
                throw new \InvalidArgumentException(
                    sprintf('Could not accept more than 3 arguments, %d given', count($params))
                );
        }

        return [$arguments, $condition];
    }
}
