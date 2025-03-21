<?php

declare(strict_types=1);

namespace Projek;

use Closure;
use Psr\Container\ContainerInterface;

/**
 * PSR-11 Container impementation class.
 *
 * @package Projek\Container
 */
class Container implements ContainerInterface
{
    /**
     * @var array<string, mixed> List of instances that been initiated.
     */
    private $entries = [];

    /**
     * @var array<string, callable> List of instance's factory to be initiate.
     */
    private $factories = [];

    /**
     * @var array<string, mixed> List of instances that been handled.
     */
    private $handledEntries = [];

    /**
     * @var Container\Resolver Service container resolver.
     */
    private $resolver;

    /**
     * Create new instance.
     *
     * @param array<string, mixed> $entries
     */
    public function __construct(array $entries = [])
    {
        $this->resolver = new Container\Resolver($this);
        $this->entries = [
            self::class => $this,
            ContainerInterface::class => $this,
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
        $this->resolver = new Container\Resolver($this);
    }

    /**
     * Retrieve the registered **entry** by $id.
     *
     * @see ContainerInterface::get()
     * @param string $id The **entry** identifier.
     * @return mixed Entry
     * @throws Container\NotFoundException
     * @throws Container\Exception
     */
    public function get(string $id)
    {
        if (! $this->has($id)) {
            throw new Container\NotFoundException($id);
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
     * Determine whether the **entry** is registered.
     *
     * {@inheritdoc}
     * @see ContainerInterface::has()
     * @param string $id The **entry** identifier.
     * @return bool
     */
    public function has(string $id): bool
    {
        return \array_key_exists($id, $this->entries);
    }

    /**
     * Registering an **entry** to the container stack.
     *
     * @link https://github.com/projek-xyz/container/wiki/registering-an-instance
     * @param string $id The **entry** identifier.
     * @param callable $factory
     * @return static
     */
    public function set(string $id, $factory): self
    {
        if ($this->has($id)) {
            return $this;
        }

        $this->factories[$id] = \is_object($factory) && ! ($factory instanceof \Closure)
            ? \get_class($factory)
            : $factory;

        $entry = $this->resolver->resolve($this->factories[$id]);

        if (\is_object($entry) && $this->isInjectable($entry)) {
            $entry->setContainer($this);
        }

        $this->entries[$id] = $entry;

        if (isset($this->handledEntries[$id])) {
            unset($this->handledEntries[$id]);
        }

        return $this;
    }

    /**
     * Resolve an instance without adding it to the stack.
     *
     * It's possible to add 2nd parameter as an array and it will pass it to
     * `Resolver::handle($instance, $args)`. While if it was a Closure, it will
     * treaten as condition.
     *
     * ```php
     * // Treat 2nd parameter as arguments
     * $container->make(SomeClass::class, ['a value'])
     *
     * // Treat 2nd parameter as condition
     * $container->make(SomeClass::class, function ($instance) {
     *     // Accepts falsy or $instance of the class
     *     return $instance instanceof CertainInterface ? [$instance, 'theMethod'] : null;
     * })
     *
     * // Treat 2nd parameter as arguments and 3rd as condition
     * $container->make(SomeClass::class, ['a value'], function ($instance) {
     *     // a condition
     * })
     * ```
     *
     * @link https://github.com/projek-xyz/container/wiki/create-an-instance
     * @param string|callable $instance String of class name or callable
     * @param list<mixed>|\Closure $args
     * @param null|\Closure $callback
     * @return mixed
     * @throws Container\InvalidArgumentException
     * @throws Container\Exception
     */
    public function make($instance, $args = [], ?\Closure $callback = null)
    {
        if (null === $callback && $args instanceof \Closure) {
            $callback = $args;
            $args = [];
        }

        if (! is_array($args)) {
            throw new Container\InvalidArgumentException(\sprintf(
                'Argument #2 must be an %s, %s given',
                (null === $callback ? 'array or instance of closure' : 'array'),
                \gettype($args)
            ));
        }

        $instance = $this->resolver->resolve(
            \is_string($instance) && isset($this->factories[$instance])
                ? $this->factories[$instance]
                : $instance,
            $args
        );

        if ($callback) {
            $instance = $callback($instance) ?: $instance;
        }

        return $this->resolver->handle($instance, $args);
    }

    /**
     * Extending an entry.
     *
     * @link https://github.com/projek-xyz/container/wiki/extending-an-instance
     * @param string $id Identifier of existing entry.
     * @param \Closure $callback Callback to extend the functionality of the entry.
     * @return object Returns the object instance.
     * @throws Container\NotFoundException If $id is not found.
     * @throws Container\Exception If trying to extends a callable.
     */
    public function extend(string $id, \Closure $callback): object
    {
        $entry = $this->get($id);

        // We treat any callable as a factory function which is might be returns
        // a different instance when it get invoked. So we should only extend an object.
        if (! \is_object($entry) || \method_exists($entry, '__invoke')) {
            throw new Container\Exception(
                \sprintf('Cannot extending a non-object or a callable entry of "%s"', $id)
            );
        }

        $extended = $this->make($callback, [$entry]);

        if (! \is_a($extended, $class = \get_class($entry))) {
            throw new Container\Exception(
                \sprintf('Argument #2 callback must be returns of type "%s"', $class)
            );
        }

        return $this->entries[$id] = $extended;
    }

    private function isInjectable(object $class): bool
    {
        return $class instanceof Container\ContainerAware && null === $class->getContainer();
    }
}
