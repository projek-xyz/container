<?php

declare(strict_types=1);

namespace Projek;

use Closure;
use Projek\Container\{ContainerInterface, Exception, Resolver};
use Psr\Container\ContainerInterface as PsrContainerInterface;

class Container implements ContainerInterface
{
    /**
     * List of instances that been initiated.
     *
     * @var array<string, mixed>
     */
    private $entries = [];

    /**
     * List of instances that been handled.
     *
     * @var array<string, mixed>
     */
    private $handledEntries = [];

    /**
     * Service container resolver.
     *
     * @var Resolver
     */
    private $resolver;

    /**
     * Create new instance.
     *
     * @param array<string, mixed> $entries
     */
    public function __construct(array $entries = [])
    {
        $this->resolver = new Resolver($this);
        $this->entries = [
            self::class => $this,
            ContainerInterface::class => $this,
            PsrContainerInterface::class => $this,
        ];

        foreach ($entries as $id => $instance) {
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

        if (isset($this->handledEntries[$id])) {
            return $this->handledEntries[$id];
        }

        $entry = $this->entries[$id];

        if (\is_object($entry) && ! \is_callable($entry)) {
            return $entry;
        }

        return $this->handledEntries[$id] = $this->resolver->handle($entry);
    }

    /**
     * {@inheritDoc}
     */
    public function has(string $id): bool
    {
        return \array_key_exists($id, $this->entries);
    }

    /**
     * {@inheritDoc}
     */
    public function set(string $id, $entry): ContainerInterface
    {
        if ($this->has($id)) {
            return $this;
        }

        $this->entries[$id] = $this->resolver->resolve($entry);

        if (isset($this->handledEntries[$id])) {
            unset($this->handledEntries[$id]);
        }

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function unset(string $id): void
    {
        unset($this->entries[$id]);

        if (isset($this->handledEntries[$id])) {
            unset($this->handledEntries[$id]);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function make($entry, ...$args)
    {
        $entry = $this->resolver->resolve($entry);

        [$args, $condition] = ($count = \count($args = \array_filter($args)))
            ? $this->assertParams($count, $args)
            : [[], null];

        if ($condition instanceof \Closure) {
            $entry = $condition($entry) ?: $entry;
        }

        return $this->resolver->handle($entry, $args);
    }

    /**
     * Extending an entry.
     *
     * @param string $id Identifier of existing entry.
     * @param Closure $callable Callback to extend the functionality of the entry.
     * @return object Returns the object instance.
     * @throws Exception\NotFoundException If $id is not found.
     * @throws Exception If trying to extends a callable.
     */
    public function extend(string $id, \Closure $callable): object
    {
        $entry = $this->get($id);

        // We tread any callable like a factory which could returns different instance
        // when it invoked. So we should only extend object instance.
        if (! \is_object($entry) || method_exists($entry, '__invoke')) {
            throw new Exception('Could not extending a non-object or callable entry of ' . $id);
        }

        $extended = $this->make($callable, [$entry]);

        if (! is_a($extended, $class = get_class($entry))) {
            throw new Exception('Argument #2 the returns value of the callback must be of type ' . $class);
        }

        $this->unset($id);

        return $this->entries[$id] = $extended;
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
