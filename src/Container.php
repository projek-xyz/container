<?php

declare(strict_types=1);

namespace Projek;

use Closure;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * PSR-11 Dependency Injection Container implementation.
 *
 * This class handles service registration, resolution, and autowiring
 * of dependencies using reflection.
 *
 * @package Projek\Container
 */
class Container implements ContainerInterface
{
    /**
     * @var Container\EntryCollector Internal storage for registered and resolved entries.
     */
    private Container\EntryCollector $entries;

    /**
     * @var array<string, Closure|callable|string> Registry of service factories or class names.
     */
    private $factories = [];

    /**
     * @var array<string, mixed> Cache of resolved singleton instances.
     */
    private $handledEntries = [];

    /**
     * @var Container\Resolver Service resolver for autowiring and factory execution.
     */
    private Container\Resolver $resolver;

    /**
     * Create a new Container instance.
     *
     * @param array<string, Closure|callable|string> $entries Initial service entries.
     * @param null|EventDispatcherInterface $eventDispatcher Optional PSR-14 event dispatcher implementation.
     */
    public function __construct(
        array $entries = [],
        private ?EventDispatcherInterface $eventDispatcher = null,
    ) {
        $this->resolver = new Container\Resolver($this);

        $defaults = [
            self::class => $this,
            ContainerInterface::class => $this,
        ];

        if ($eventDispatcher) {
            $defaults[EventDispatcherInterface::class] = $eventDispatcher;
        }

        $this->entries = new Container\EntryCollector($defaults);

        foreach ($entries as $id => $factory) {
            $this->set($id, $factory);
        }
    }

    /**
     * Clone the container with a new resolver instance.
     *
     * Ensures that cloned containers have their own isolated state
     * while sharing the same initial entries.
     */
    public function __clone()
    {
        $this->resolver = new Container\Resolver($this);
        $this->entries = new Container\EntryCollector($this->entries);
    }

    /**
     * Dispatch a container lifecycle event.
     *
     * This internal method handles the communication with the PSR-14
     * event dispatcher if one is provided.
     *
     * @param object $event The event object to dispatch.
     * @return void
     */
    private function dispatch(object $event): void
    {
        $this->eventDispatcher?->dispatch($event);
    }

    /**
     * Assign a PSR-14 event dispatcher implementation.
     *
     * This library provides lifecycle events and a ListenerProvider for internal
     * features, but the actual EventDispatcherInterface implementation (e.g.
     * Symfony EventDispatcher) must be provided by the developer.
     *
     * @link https://github.com/projek-xyz/container/wiki/event-lifecycle Event Lifecycle Wiki
     * @param EventDispatcherInterface $eventDispatcher The event dispatcher instance.
     * @return self
     */
    public function setEventDispatcher(EventDispatcherInterface $eventDispatcher): self
    {
        $this->eventDispatcher = $eventDispatcher;

        return $this;
    }

    /**
     * {@inheritdoc}
     *
     * Note: If the resolved entry is a callable object (has an `__invoke` method),
     * this method will return the result of the invocation rather than the object itself.
     *
     * @throws Container\NotFoundException If the entry is not found.
     * @throws Container\Exception If the entry cannot be resolved.
     */
    public function get(string $id)
    {
        $this->dispatch($pre = new Container\Events\BeforeResolution($id));

        // Allows service redirection via event.
        $id = $pre->id;

        if (isset($this->handledEntries[$id])) {
            return $this->handledEntries[$id];
        }

        $this->dispatch($instance = new Container\Events\AfterResolution(
            $this->entries[$id],
            $id,
        ));

        $entry = $instance->getEntry();

        if (\is_object($entry) && ! \is_callable($entry)) {
            return $entry;
        }

        /** @var array{object|string,string}|callable|object|string $entry */
        return $this->handledEntries[$id] = $this->resolver->handle($entry);
    }

    /**
     * Check if an entry is registered in the container.
     *
     * {@inheritdoc}
     * @see ContainerInterface::has()
     * @param string $id The entry identifier.
     * @return bool
     */
    public function has(string $id): bool
    {
        return $this->entries->offsetExists($id);
    }

    /**
     * Register a new service factory or class in the container.
     *
     * If the ID is already registered, the new factory will be ignored.
     * Use the `extend()` method to modify existing services.
     *
     * Note: If a class name or object is provided that has an `__invoke` method,
     * it will be treated as a factory, and `get()` will return the result of that invocation.
     *
     * @link https://github.com/projek-xyz/container/wiki/registering-an-instance Registering an Instance Wiki
     * @param string $id The entry identifier.
     * @param Closure|callable|string|object $factory A factory closure, callable, class name, or object instance.
     * @return static
     */
    public function set(string $id, $factory): static
    {
        if ($this->entries->offsetExists($id)) {
            return $this;
        }

        $this->factories[$id] = \is_object($factory) && ! ($factory instanceof Closure)
            ? \get_class($factory)
            : $factory;

        $this->dispatch($reg = new Container\Events\BeforeRegistration(
            $this->factories[$id],
            $id,
        ));

        $this->entries[$id] = $this->resolver->resolve($reg->getFactory());

        if (isset($this->handledEntries[$id])) {
            unset($this->handledEntries[$id]);
        }

        $this->dispatch(new Container\Events\AfterRegistration(
            $this->entries[$id],
            $id,
        ));

        return $this;
    }

    /**
     * Create a new instance without registering it as a singleton.
     *
     * This method resolves dependencies on-the-fly and allows passing
     * additional arguments or conditions for the resolution process.
     *
     * Note: The `$args` are used for constructor resolution for normal class instantiation.
     * For invokable classes (e.g. `__invoke` or a method identified by `$condition`),
     * constructor args are ignored and `$args` are passed to the callable handler.
     *
     * ```php
     * // Pass arguments directly
     * $container->make(SomeClass::class, ['a value'])
     *
     * // Use a condition to determine the method to call
     * $container->make(SomeClass::class, function ($instance) {
     *     return $instance instanceof CertainInterface ? [$instance, 'theMethod'] : null;
     * })
     * ```
     *
     * @template TObj of object
     * @template TArgs of array<int, mixed>
     *
     * @link https://github.com/projek-xyz/container/wiki/create-an-instance Creating an Instance Wiki
     * @param Closure|callable|string|object $instance Class name, factory, or object instance.
     * @param TArgs|Closure(TObj):?TObj $args Optional arguments or a condition closure.
     * @param null|Closure(TObj):?TObj $condition Optional condition closure if $args is an array.
     * @return mixed
     * @throws Container\InvalidArgumentException If arguments are invalid.
     * @throws Container\Exception If resolution fails.
     */
    public function make($instance, $args = [], ?Closure $condition = null): mixed
    {
        if (null === $condition && $args instanceof Closure) {
            $condition = $args;
            $args = [];
        }

        if (! is_array($args)) {
            throw new Container\InvalidArgumentException(\sprintf(
                'Argument #2 must be an %s, %s given',
                (null === $condition ? 'array or instance of closure' : 'array'),
                \gettype($args)
            ));
        }

        $instance = $this->resolver->resolve(
            \is_string($instance) && isset($this->factories[$instance])
                ? $this->factories[$instance]
                : $instance,
            $args
        );

        if ($condition) {
            $instance = $condition($instance) ?: $instance;
        }

        return $this->resolver->handle($instance, $args);
    }

    /**
     * Extend an existing service with additional functionality.
     *
     * This method retrieves an existing entry and passes it to the provided
     * callback. The callback must return the modified or wrapped instance.
     *
     * Note: The callback must return an object instance that is of the same
     * type or a subclass of the original service.
     *
     * @link https://github.com/projek-xyz/container/wiki/extending-an-instance Extending an Instance Wiki
     * @param string $id Identifier of the existing entry.
     * @param Closure(object):object $callback Callback to extend the service.
     * @return object Returns the extended object instance.
     * @throws Container\NotFoundException If the entry ID is not found.
     * @throws Container\Exception If trying to extend a non-object or callable.
     */
    public function extend(string $id, Closure $callback): object
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
        $class = \get_class($entry);

        if (! \is_object($extended) || ! \is_a($extended, $class)) {
            throw new Container\Exception(
                \sprintf('Argument #2 callback must be returns of type "%s"', $class)
            );
        }

        return $this->entries[$id] = $extended;
    }
}
