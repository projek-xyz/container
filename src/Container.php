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
use ReflectionClass;
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
     * @var bool True while the ResolverInterface entry itself is mid-build.
     *           Its build pulls the shared handler, whose constructor pulls
     *           this very entry back — the bootstrap guard in get() reads it.
     */
    private bool $buildingResolver = false;

    /**
     * @var bool True while the EventDispatcherInterface entry itself is
     *           mid-build: any event dispatched in that window would need the
     *           dispatcher being built, so it waits in $deferredEvents.
     */
    private bool $buildingDispatcher = false;

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
     * @param  array<string, mixed>  $entries  Initial service entries.
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
     * Clone the container: every entry is cloned (singleton caches reset,
     * registrations/decorators/metadata carry over) and the three
     * self-referential auto defaults are re-pointed to the clone — their
     * factory closures capture $this.
     */
    public function __clone()
    {
        $entries = new EntryCollector;

        foreach ($this->entries as $id => $entry) {
            $entries[$id] = clone $entry;
        }

        $this->entries = $entries;
        $this->handler = null;

        foreach ([self::class, ContainerInterface::class, EventDispatcherInterface::class] as $id) {
            $entry = $this->entries->offsetExists($id) ? $this->entries->offsetGet($id) : null;

            if ($entry instanceof CallableEntry && $entry->auto) {
                // Recreate the factory closure against the clone; rebinding
                // keeps any captured value (the ctor-provided dispatcher)
                // while re-pointing the captured container.
                $this->entries[$id] = new CallableEntry(
                    $id,
                    Closure::bind($entry->factory, $this, self::class),
                    auto: true,
                );
            }
        }
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
     * Dispatcher swapping is an infrastructure setter: it force-replaces the
     * entry (bypassing duplicate strictness) and fires EntryRegistered like
     * any other registration. The closure wrapper is mandatory — objects are
     * not factories.
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

        $this->dispatch(
            new EntryRegistered($entry)
        );

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
     * Resolve a registered entry, building and caching it on first use.
     *
     * {@inheritdoc}
     *
     * @link https://github.com/projek-xyz/container/wiki/event-lifecycle Event Lifecycle Wiki
     *
     * @throws NotFoundException If the entry is not found.
     * @throws ResolutionException If the entry cannot be built.
     */
    public function get(string $id)
    {
        $entry = $this->entries->offsetGet($id);

        if ($id === ResolverInterface::class && $this->buildingResolver && $this->handler === null) {
            // Bootstrap guard (§12): the shared handler's constructor pulls
            // this id, and that build needs the very handler being
            // constructed. Hand it the default resolver — the same fallback
            // Handler applies when no resolver entry exists — so the cycle
            // terminates; the outer request still builds the real entry.
            return new Resolver($this);
        }

        if (! $entry->isBuilt()) {
            try {
                $entry->beginBuild();

                $isResolver = $id === ResolverInterface::class;
                $isDispatcher = $id === EventDispatcherInterface::class;

                if ($isResolver) {
                    $this->buildingResolver = true;
                }

                if ($isDispatcher) {
                    $this->buildingDispatcher = true;
                }

                try {
                    $value = $entry->build($this->getHandler(), $this);
                } finally {
                    if ($isResolver) {
                        $this->buildingResolver = false;
                    }

                    if ($isDispatcher) {
                        $this->buildingDispatcher = false;
                    }

                    $entry->endBuild();
                }

                // Cache strictly before dispatch: an EntryResolved listener
                // may re-enter get() for this very id.
                $entry->cache($value);

                if (! $entry->auto && ! $entry instanceof AliasEntry && \is_object($value)) {
                    $this->dispatch(new EntryResolved($id, $value));
                }

                if ($isDispatcher) {
                    // Flush events raised while this dispatcher was being
                    // built (its own build completes before the first
                    // dispatch, so the lookup below is always a cache hit).
                    $deferred = $this->deferredEvents;
                    $this->deferredEvents = [];

                    foreach ($deferred as $event) {
                        $this->dispatch($event);
                    }
                }
            } catch (Throwable $e) {
                throw $this->boundary($e, $id);
            }
        }

        return $entry->value();
    }

    /**
     * Check if an entry is registered in the container.
     *
     * {@inheritdoc}
     *
     * @see ContainerInterface::has()
     *
     * @param  string  $id  The entry identifier.
     */
    public function has(string $id): bool
    {
        return $this->entries->offsetExists($id);
    }

    /**
     * Register a new service factory or class in the container.
     *
     * The factory is classified (§6 dispatch) but never built — registration
     * is lazy. Duplicate user registrations throw; infrastructure (auto)
     * defaults may be replaced.
     *
     * @link https://github.com/projek-xyz/container/wiki/registering-an-instance Registering an Instance Wiki
     *
     * @param  string  $id  The entry identifier.
     * @param  mixed  $factory  A factory closure, callable, class name, pair, or EntryFactory.
     *
     * @throws InvalidArgumentException If the id is a duplicate or the factory is invalid.
     */
    public function set(string $id, mixed $factory): static
    {
        if ($this->entries->offsetExists($id) && ! $this->entries->offsetGet($id)->auto) {
            throw InvalidArgumentException::alreadyRegistered($id);
        }

        // The wrapping parens are load-bearing: Kahlan only records a
        // statement's begin line when a paren group survives to the `;` —
        // without them the closing `});` line can never be marked covered.
        $entry = (match (true) {
            // row 1 — the is_object guard is load-bearing: a class-string
            // naming an invokable class must fall through to the ClassName
            // arm (build, never invoke).
            $factory instanceof Closure
                || (\is_object($factory) && \method_exists($factory, '__invoke')) => new CallableEntry($id, $factory),
            $factory instanceof EntryFactory => new FactoryEntry($id, $factory),
            \is_string($factory) && \str_contains($factory, '::') => new MethodPairEntry($id, $factory),
            \is_string($factory) && \class_exists($factory)
                && (new ReflectionClass($factory))->isInstantiable() => new ClassNameEntry($id, $factory),
            \is_string($factory) && \function_exists($factory) => new CallableEntry($id, $factory),
            // any other string — incl. non-buildable type symbols (interface,
            // trait, abstract class, enum): must name a pre-registered entry
            // (the typo catcher).
            \is_string($factory) => $this->has($factory)
                ? new AliasEntry($id, $factory)
                : throw InvalidArgumentException::unresolvableString($id, $factory),
            \is_array($factory) => new MethodPairEntry($id, $factory),   // pair validation in its ctor (row 4)
            // row 5 — plain objects only; invokables matched row 1 above.
            // (\is_object, not `instanceof object` — the latter always
            // evaluates false: `object` is parsed as a class name.)
            \is_object($factory) => throw InvalidArgumentException::plainObjectNotAFactory($id, $factory),
            // row 6 — invalid factory of type %s.
            default => throw InvalidArgumentException::invalidFactoryType($id, $factory),
        });

        $this->entries[$id] = $entry;

        $this->dispatch(new EntryRegistered($entry));

        return $this;
    }

    /**
     * Create a new instance without registering it as a singleton.
     *
     * Accepts exactly four families: a registered id, an unregistered
     * instantiable class-string, a callable shape, or nothing else (throws).
     * Results are never cached and no events are dispatched — ContainerAware
     * injection is direct.
     *
     * @link https://github.com/projek-xyz/container/wiki/create-an-instance Creating an Instance Wiki
     *
     * @param  array|callable|object|string  $instance  Registered id, class name, or callable shape.
     * @param  array<mixed>  $args  Positional/named arguments for the invocation or constructor.
     *
     * @throws InvalidArgumentException If the input matches no family.
     * @throws ResolutionException If a package call fails to resolve.
     */
    public function make(array|callable|object|string $instance, array $args = []): mixed
    {
        try {
            // row 1 — a registered id wins even when it also looks like a
            // class or a function.
            if (\is_string($instance) && $this->entries->offsetExists($instance)) {
                $aliases = [];
                $current = $this->entries->offsetGet($instance);

                while ($current instanceof AliasEntry) {
                    $aliases[] = $current;
                    $current = $this->entries->offsetGet($current->factory);
                }

                $current->beginBuild();

                $isResolver = $current->id === ResolverInterface::class;

                if ($isResolver) {
                    // make() entered the resolver's build before the shared
                    // handler existed — mark it so the handler constructor's
                    // pull of this id takes the bootstrap guard in get().
                    $this->buildingResolver = true;
                }

                try {
                    $value = $current->build($this->getHandler(), $this, $args);
                } finally {
                    if ($isResolver) {
                        $this->buildingResolver = false;
                    }

                    $current->endBuild();
                }

                // The aliases' own decorators never ran — make() bypassed
                // their build(); innermost first, mirroring get().
                $handler = $this->getHandler();

                foreach (\array_reverse($aliases) as $alias) {
                    $value = $alias->applyDecorators($handler, $value);
                }

                return $this->injectContainer($value);
            }

            // row 2 — an unregistered, instantiable class-string builds
            // transiently through the same ClassNameEntry path as set()
            // (never stored, no cache, no events, zero decorators).
            if (
                \is_string($instance)
                && ! \str_contains($instance, '::')
                && \class_exists($instance)
                && (new ReflectionClass($instance))->isInstantiable()
            ) {
                $value = (new ClassNameEntry($instance, $instance))
                    ->build($this->getHandler(), $this, $args);

                return $this->injectContainer($value);
            }

            // row 3 — structural callable shape only; contents are validated
            // by the package (an invokable class-string can never land here —
            // row 2 runs first: build, never invoke).
            // Parens as in set() — they let Kahlan attribute the `});`
            // terminator line to the statement for coverage.
            $shape = (match (true) {
                $instance instanceof Closure => true,
                \is_object($instance) && \method_exists($instance, '__invoke') => true,
                \is_string($instance) && \function_exists($instance) => true,
                \is_string($instance) && \str_contains($instance, '::') => true,
                \is_array($instance) => isset($instance[0], $instance[1]),
                default => false,
            });

            if ($shape) {
                return $this->injectContainer(
                    $this->getHandler()->handle($instance, $args)
                );
            }

            // row 4 — thrown from inside the boundary: the InvalidArgumentException
            // passes it untouched (§13 rule 4).
            throw \is_object($instance)
                ? InvalidArgumentException::cannotMakePlainObject($this->describeTarget($instance))
                : InvalidArgumentException::cannotMakeUnsupported($this->describeTarget($instance));
        } catch (Throwable $e) {
            throw $this->boundary($e, $this->describeTarget($instance));
        }
    }

    /**
     * Extend an existing service with a decorator.
     *
     * Never forces a build: a pending decorator joins the entry's list and
     * applies inside the next build; an already-built entry is decorated
     * immediately (apply-then-append) and re-cached.
     *
     * @link https://github.com/projek-xyz/container/wiki/extending-an-instance Extending an Instance Wiki
     *
     * @param  string  $id  Identifier of the existing entry.
     * @param  Closure  $callback  Decorator declaring an explicit single class return type.
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

        $declared = $returnType->getName();

        if ($target !== 'object' && ! \is_a($declared, $target, true)) {
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
     * Send one event to the effective dispatcher. While the dispatcher entry
     * itself is mid-build (bootstrap: its build pulls the handler, whose
     * constructor pulls the resolver, whose replacement entry dispatches
     * EntryResolved) the event waits in $deferredEvents instead of forcing a
     * circular rebuild — get() flushes the queue right after caching it.
     */
    private function dispatch(object $event): void
    {
        if ($this->buildingDispatcher) {
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
}
