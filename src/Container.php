<?php

declare(strict_types=1);

namespace Projek;

use Closure;
use Projek\Callable\Handler;
use Projek\Callable\Resolver;
use Projek\Callable\ResolverExceptionInterface;
use Projek\Callable\ResolverInterface;
use Projek\Container\ContainerAware;
use Projek\Container\Entry\AliasEntry;
use Projek\Container\Entry\CallableEntry;
use Projek\Container\Entry\ClassNameEntry;
use Projek\Container\Entry\FactoryEntry;
use Projek\Container\Entry\MethodPairEntry;
use Projek\Container\EntryCollector;
use Projek\Container\EntryFactory;
use Projek\Container\Events\Dispatcher;
use Projek\Container\Events\EntryRegistered;
use Projek\Container\Events\EntryResolved;
use Projek\Container\InvalidArgumentException;
use Projek\Container\NotFoundException;
use Projek\Container\ResolutionException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use ReflectionFunction;
use ReflectionNamedType;
use Throwable;

/**
 * PSR-11 Dependency Injection Container implementation.
 *
 * Registrations are lazy `Entry` objects: `set()` classifies and stores,
 * `get()` builds singletons through the error boundary, `make()` always builds
 * fresh, and `extend()` appends decorators.
 */
class Container implements ContainerInterface
{
    /**
     * @var EntryCollector Internal storage for registered entries.
     */
    private EntryCollector $entries;

    /**
     * @var Handler|null Shared handler, built lazily against this container.
     */
    private ?Handler $handler = null;

    /**
     * @var list<object> Events raised while the dispatcher was mid-build,
     *                   flushed right after it is cached.
     */
    private array $deferredEvents = [];

    /**
     * Create a new Container instance.
     *
     * Infrastructure defaults are inserted directly (no events); user entries
     * are registered through set(), which fires EntryRegistered.
     *
     * @template T of object
     *
     * @param  array<string, array{class-string<T>|T,string}|callable|string|EntryFactory>  $entries  Initial service entries.
     * @param  null|EventDispatcherInterface  $eventDispatcher  Optional PSR-14 event dispatcher implementation.
     */
    public function __construct(
        array $entries = [],
        ?EventDispatcherInterface $eventDispatcher = null,
    ) {
        $this->entries = new EntryCollector;

        $defaults = [
            self::class => fn (): self => $this,
            ContainerInterface::class => fn (): ContainerInterface => $this,
            ResolverInterface::class => fn (ContainerInterface $c): ResolverInterface => new Resolver($c),
            EventDispatcherInterface::class => fn (): EventDispatcherInterface => $eventDispatcher ?? new Dispatcher($this),
        ];

        foreach ($defaults as $id => $factory) {
            $this->entries[$id] = new CallableEntry($id, $factory, auto: true);
        }

        foreach ($entries as $id => $factory) {
            $this->set($id, $factory);
        }
    }

    /**
     * Clone the container: every entry is cloned (singleton caches reset, registrations/
     * decorators/metadata carry over) and the three self-referential auto defaults
     * are re-pointed to the clone — their factory closures capture $this.
     */
    public function __clone()
    {
        $entries = new EntryCollector;
        $defaults = [self::class, ContainerInterface::class, EventDispatcherInterface::class];

        foreach ($this->entries as $id => $entry) {
            if (! $entry->auto || ! in_array($id, $defaults, true)) {
                $entries[$id] = clone $entry;

                continue;
            }

            // Recreate the factory closure against the clone; rebinding keeps any captured
            // value (the ctor-provided dispatcher) while re-pointing the captured container.
            /** @var CallableEntry $entry */
            $entries[$id] = new CallableEntry(
                $id,
                Closure::bind($entry->factory, $this, self::class),
                auto: $entry->auto,
            );
        }

        $this->entries = $entries;
        $this->handler = null;
    }

    /**
     * Retrieve a PSR-14 event dispatcher instance.
     *
     * When no implementation is provided by the developer, a minimalist
     * internal implementation is built lazily on first use.
     */
    final public function getEventDispatcher(): EventDispatcherInterface
    {
        return $this->get(EventDispatcherInterface::class);
    }

    /**
     * Assign a PSR-14 event dispatcher implementation.
     *
     * Dispatcher swapping is an infrastructure setter: it force-replaces the entry
     * (bypassing duplicate strictness) and fires EntryRegistered like any other
     * registration. The closure wrapper is mandatory — objects are not factories.
     *
     * @link https://github.com/projek-xyz/container/wiki/event-lifecycle Event Lifecycle Wiki
     *
     * @param  EventDispatcherInterface  $eventDispatcher  The event dispatcher instance.
     */
    final public function setEventDispatcher(EventDispatcherInterface $eventDispatcher): self
    {
        $entry = new CallableEntry(
            EventDispatcherInterface::class,
            fn (): EventDispatcherInterface => $eventDispatcher,
        );

        $this->entries[EventDispatcherInterface::class] = $entry;

        $this->dispatch(new EntryRegistered($entry));

        return $this;
    }

    /**
     * Retrieve the effective service resolver — a singleton resolved through
     * the normal path, so a user override flows automatically.
     */
    final public function getResolver(): ResolverInterface
    {
        return $this->get(ResolverInterface::class);
    }

    /**
     * Check if an entry is registered in the container.
     *
     * {@inheritdoc}
     *
     * @see ContainerInterface::has()
     *
     * @param  class-string|string  $id  The entry identifier.
     */
    public function has(string $id): bool
    {
        return $this->entries->offsetExists($id);
    }

    /**
     * Resolve a registered entry, building and caching it on first use.
     *
     * {@inheritdoc}
     *
     * @link https://github.com/projek-xyz/container/wiki/event-lifecycle Event Lifecycle Wiki
     *
     * @template T of object
     *
     * @param  class-string<T>|string  $id  The entry identifier.
     * @return ($id is class-string<T> ? T : mixed)
     *
     * @throws NotFoundException If the entry is not found.
     * @throws ResolutionException If the entry cannot be built.
     */
    public function get(string $id)
    {
        if ($id === ResolverInterface::class && $this->isBuilding(ResolverInterface::class) && $this->handler === null) {
            // Bootstrap guard: the shared handler's constructor pulls this id, and that build
            // needs the very handler being constructed. Hand it the default resolver — the same
            // fallback Handler applies when no resolver entry exists — so the cycle terminates;
            // the outer request still builds the real entry.
            return new Resolver($this);
        }

        $entry = $this->entries->offsetGet($id);

        if ($entry->isBuilt()) {
            return $entry->value();
        }

        $entry->beginBuild();

        try {
            $value = $entry->build($this->getHandler(), $this);
        } catch (Throwable $e) {
            throw $this->boundary($e, $id);
        } finally {
            $entry->endBuild();
        }

        // Cache strictly before dispatch: an EntryResolved listener
        // may re-enter get() for this very id.
        $entry->cache($value);

        if (! $entry->auto && ! $entry instanceof AliasEntry && \is_object($value)) {
            $this->dispatch(new EntryResolved($id, $value));
        }

        if ($id === EventDispatcherInterface::class) {
            // Flush events raised while this dispatcher was being built (its own build completes
            // before the first dispatch, so the lookup below is always a cache hit).
            $deferred = $this->deferredEvents;
            $this->deferredEvents = [];

            foreach ($deferred as $event) {
                $this->dispatch($event);
            }
        }

        return $entry->value();
    }

    /**
     * Register a new service factory or class in the container.
     *
     * The factory is classified but never built — registration is lazy.
     * Duplicate user registrations throw; infrastructure (auto) defaults may be replaced.
     *
     * @link https://github.com/projek-xyz/container/wiki/registering-an-instance Registering an Instance Wiki
     *
     * @template T of object
     *
     * @param  class-string<T>|string  $id  The entry identifier.
     * @param  array{class-string<T>|T,string}|callable|string|EntryFactory  $factory  A factory closure, callable, class name, pair, or EntryFactory.
     *
     * @throws InvalidArgumentException If the id is a duplicate or the factory is invalid.
     */
    public function set(string $id, mixed $factory): static
    {
        if ($this->entries->offsetExists($id) && ! $this->entries->offsetGet($id)->auto) {
            throw InvalidArgumentException::alreadyRegistered($id);
        }

        // The wrapping parens are load-bearing: Kahlan only records a statement's
        // begin line when a paren group survives to the `;` — without them
        // the closing `});` line can never be marked covered.
        $this->entries[$id] = (match (true) {
            // Invokable shapes first — the is_object guard is load-bearing: a class-string naming
            // an invokable class must fall through to the ClassName arm (build, never invoke).
            FactoryEntry::isValid($factory) => new FactoryEntry($id, $factory),
            CallableEntry::isValid($factory) => new CallableEntry($id, $factory),
            ClassNameEntry::isValid($factory) => new ClassNameEntry($id, $factory),
            MethodPairEntry::isValid($factory) => new MethodPairEntry($id, $factory),
            // any other string — incl. non-buildable type symbols (interface, trait, abstract
            // class, enum): must name a pre-registered entry (the typo catcher).
            \is_string($factory) => $this->has($factory)
                ? new AliasEntry($id, $factory)
                : throw InvalidArgumentException::unresolvableString($id, $factory),
            // Plain objects only; invokables matched the arm above. (\is_object, not
            // `instanceof object` — the latter always evaluates false: `object` is parsed as a class name.)
            \is_object($factory) => throw InvalidArgumentException::plainObjectNotAFactory($id, $factory),
            // Invalid factory of type %s.
            default => throw InvalidArgumentException::invalidFactoryType($id, $factory),
        });

        $this->dispatch(new EntryRegistered($this->entries[$id]));

        return $this;
    }

    /**
     * Create a new instance without registering it as a singleton.
     *
     * Accepts exactly four families: a registered id, an unregistered instantiable
     * class-string, a callable shape, or nothing else (throws). Results are never
     * cached and no events are dispatched — ContainerAware injection is direct.
     *
     * @link https://github.com/projek-xyz/container/wiki/create-an-instance Creating an Instance Wiki
     *
     * @template T of object
     *
     * @param  array{class-string<T>|T,string}|callable|callable-string  $instance  Registered id, class name, or callable shape.
     * @param  array<mixed>  $args  Positional/named arguments for the invocation or constructor.
     * @return ($instance is class-string<T> ? T : mixed)
     *
     * @throws InvalidArgumentException If the input matches no family.
     * @throws ResolutionException If a package call fails to resolve.
     */
    public function make(array|callable|object|string $instance, array $args = []): mixed
    {
        // A registered id wins even when it also looks like a class or a function.
        if (\is_string($instance) && $this->entries->offsetExists($instance)) {
            $aliases = [];
            $entry = $this->entries->offsetGet($instance);

            while ($entry instanceof AliasEntry) {
                $aliases[] = $entry;
                $entry = $this->entries->offsetGet($entry->factory);
            }

            $entry->beginBuild();

            $handler = $this->getHandler();

            try {
                $value = $entry->build($handler, $this, $args);
            } catch (Throwable $e) {
                throw $this->boundary($e, $instance);
            } finally {
                $entry->endBuild();
            }

            // The aliases' own decorators never ran — make() bypassed
            // their build(); innermost first, mirroring get().
            foreach (\array_reverse($aliases) as $alias) {
                $value = $alias->applyDecorators($handler, $value);
            }

            return $this->injectContainer($value);
        }

        // An unregistered, instantiable class-string builds transiently through the same
        // ClassNameEntry path as set() (never stored, no cache, no events, zero decorators).
        if (ClassNameEntry::isValid($instance)) {
            try {
                $value = (new ClassNameEntry($instance, $instance))
                    ->build($this->getHandler(), $this, $args);
            } catch (Throwable $e) {
                throw $this->boundary($e, $instance);
            }

            return $this->injectContainer($value);
        }

        // Structural callable shape only; contents are validated by the package
        // (an invokable class-string can never land here — the class-string arm
        // runs first: build, never invoke).
        if (CallableEntry::isValid($instance) || MethodPairEntry::isValid($instance)) {
            try {
                /** @var callable $instance */
                return $this->injectContainer(
                    $this->getHandler()->handle($instance, $args)
                );
            } catch (Throwable $e) {
                throw $this->boundary($e, $this->describeTarget($instance));
            }
        }

        throw \is_object($instance)
            ? InvalidArgumentException::cannotMakePlainObject($this->describeTarget($instance))
            : InvalidArgumentException::cannotMakeUnsupported($this->describeTarget($instance));
    }

    /**
     * Extend an existing service with a decorator.
     *
     * Never forces a build: a pending decorator joins the entry's list and applies inside
     * the next build; an already-built entry is decorated immediately (apply-then-append) and re-cached.
     *
     * @link https://github.com/projek-xyz/container/wiki/extending-an-instance Extending an Instance Wiki
     *
     * @template T of object
     *
     * @param  class-string<T>|string  $id  Identifier of the existing entry.
     * @param  Closure(T $instance, mixed ...$args):T  $callback  Decorator declaring an explicit single class return type.
     *
     * @throws NotFoundException If the entry id is absent.
     * @throws InvalidArgumentException If the target or the callback return type is invalid.
     */
    public function extend(string $id, Closure $callback): static
    {
        $entry = $this->entries->offsetGet($id);
        $target = $entry->extensionTarget($this->entries);

        if ($target === null) {
            throw InvalidArgumentException::extensionTargetNotDerivable($id);
        }

        $returnType = (new ReflectionFunction($callback))->getReturnType();

        if (! $returnType instanceof ReflectionNamedType || $returnType->isBuiltin()) {
            throw InvalidArgumentException::callbackReturnTypeInvalid($id);
        }

        if ($target !== 'object' && ! \is_a($returnType->getName(), $target, true)) {
            throw InvalidArgumentException::callbackReturnMismatch($id, $target);
        }

        if ($entry->isBuilt()) {
            // Apply-then-append: the callback joins the list only after it ran
            // successfully, then the result replaces the cached value — list
            // and cache can never drift.
            $value = $this->getHandler()->handle($callback, [$entry->value()]);

            $entry->decorate($callback);
            $entry->cache($value);
        } else {
            $entry->decorate($callback);
        }

        return $this;
    }

    /**
     * Send one event to the effective dispatcher. While the dispatcher entry itself is mid-build
     * (bootstrap: its build pulls the handler, whose constructor pulls the resolver, whose
     * replacement entry dispatches EntryResolved) the event waits in $deferredEvents instead of
     * forcing a circular rebuild — get() flushes the queue right after caching it.
     */
    private function dispatch(object $event): void
    {
        if ($this->isBuilding(EventDispatcherInterface::class)) {
            $this->deferredEvents[] = $event;

            return;
        }

        $this->getEventDispatcher()->dispatch($event);
    }

    /**
     * The error boundary shared by get() and make(): walk the previous chain
     * for the genuine missing-id witness, keep an existing ResolutionException
     * as-is, wrap package failures, rethrow user code untouched.
     */
    private function boundary(Throwable $e, string $label): Throwable
    {
        for ($cause = $e; $cause !== null; $cause = $cause->getPrevious()) {
            if ($cause instanceof NotFoundExceptionInterface) {
                return $cause;
            }
        }

        if ($e instanceof ResolutionException) {
            return $e;
        }

        if ($e instanceof ResolverExceptionInterface || $e instanceof ContainerExceptionInterface) {
            return new ResolutionException(
                \sprintf('Failed to resolve "%s": %s', $label, $e->getMessage()),
                $e
            );
        }

        return $e;
    }

    /**
     * Label a make() target for boundary messages: the id/string as-is, a
     * pair as Class::method, otherwise the debug type.
     */
    private function describeTarget(mixed $instance): string
    {
        if (\is_string($instance)) {
            return $instance;
        }

        if (\is_array($instance) && isset($instance[0], $instance[1])) {
            return \sprintf(
                '%s::%s',
                \is_string($instance[0]) ? $instance[0] : \get_debug_type($instance[0]),
                \is_string($instance[1]) ? $instance[1] : \get_debug_type($instance[1]),
            );
        }

        return \get_debug_type($instance);
    }

    /**
     * Direct ContainerAware injection for make() results — option B: no event
     * dispatch from make().
     */
    private function injectContainer(mixed $value): mixed
    {
        if ($value instanceof ContainerAware && $value->getContainer() === null) {
            $value->setContainer($this);
        }

        return $value;
    }

    /**
     * The shared handler, built lazily so it always rides the effective
     * resolver of this container.
     */
    private function getHandler(): Handler
    {
        return $this->handler ??= new Handler($this);
    }

    /**
     * Whether the entry $id is mid-build.
     *
     * @see Entry::building()
     */
    private function isBuilding(string $id): bool
    {
        return $this->entries[$id]->building();
    }
}
