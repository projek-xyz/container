<?php

declare(strict_types=1);

use Projek\Callable\Resolver;
use Projek\Callable\ResolverInterface;
use Projek\Container;
use Projek\Container\Entry;
use Projek\Container\EntryFactory;
use Projek\Container\Events;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\EventDispatcher\ListenerProviderInterface;
use Psr\EventDispatcher\StoppableEventInterface;
use Stubs\AbstractFoo;
use Stubs\ByRefStub;
use Stubs\CallableClass;
use Stubs\CertainInterface;
use Stubs\ConcreteBar;
use Stubs\CouldExtends;
use Stubs\Dummy;
use Stubs\HasContainerClass;
use Stubs\InstantiableClass;
use Stubs\MultiParamStub;
use Stubs\RecordingDispatcher;
use Stubs\SomeClass;
use Stubs\SpyResolver;
use Stubs\VariadicStub;

use function Kahlan\beforeEach;
use function Kahlan\context;
use function Kahlan\describe;
use function Kahlan\expect;
use function Kahlan\it;

describe(Container::class, function () {
    beforeEach(function () {
        $this->provider = new Events\ListenerProvider;
        $this->recorder = new RecordingDispatcher($this->provider);
        $this->c = new Container([], $this->recorder);
        $this->provider->setContainer($this->c);
    });

    context('::get', function () {
        it('should resolve a registered entry and cache it as a singleton', function () {
            $calls = 0;
            $this->c->set('foo', function () use (&$calls) {
                $calls++;

                return new stdClass;
            });

            $first = $this->c->get('foo');
            $second = $this->c->get('foo');

            expect($first)->toBeAnInstanceOf(stdClass::class);
            expect($first)->toBe($second);
            expect($calls)->toBe(1);
        });

        it('should auto-wire registered dependencies', function () {
            $this->c->set(Dummy::class, Dummy::class);
            $this->c->set('svc', fn (Dummy $dummy) => $dummy);

            expect($this->c->get('svc'))->toBe($this->c->get(Dummy::class));
        });

        it('should dispatch EntryResolved once — cache hits dispatch nothing', function () {
            $this->c->set('foo', fn () => new stdClass);

            $one = $this->c->get('foo');
            $two = $this->c->get('foo');

            $resolved = $this->recorder->eventsFor(Events\EntryResolved::class);

            expect($two)->toBe($one);
            expect($resolved)->toHaveLength(1);
            expect($resolved[0]->id)->toBe('foo');
            expect($resolved[0]->instance)->toBe($one);
        });

        it('should cache strictly before dispatch — a listener re-entering get() of the same id hits the cache', function () {
            $c = null;
            $reentered = null;

            $listener = function (Events\EntryResolved $event) use (&$c, &$reentered): void {
                // The value must already be cached: no circular guard trip,
                // no rebuild, no second event for the inner get().
                /** @var Container $c */
                $reentered = $c->get($event->id);
            };

            $provider = new class($listener) implements ListenerProviderInterface
            {
                public function __construct(private mixed $listener) {}

                public function getListenersForEvent(object $event): iterable
                {
                    return $event instanceof Events\EntryResolved ? [$this->listener] : [];
                }
            };
            $recorder = new RecordingDispatcher($provider);

            $c = new Container([], $recorder);
            $c->set('foo', fn () => new stdClass);

            $first = $c->get('foo');
            $resolved = $recorder->eventsFor(Events\EntryResolved::class);

            expect($reentered)->toBe($first);
            expect($resolved)->toHaveLength(1);
            expect($resolved[0]->id)->toBe('foo');
            expect($resolved[0]->instance)->toBe($first);
        });

        it('should keep the entry cached when an EntryResolved listener throws — no un-cache, no replay', function () {
            $boom = new RuntimeException('listener boom');
            $calls = 0;

            $provider = new class($boom) implements ListenerProviderInterface
            {
                public function __construct(private RuntimeException $boom) {}

                public function getListenersForEvent(object $event): iterable
                {
                    return $event instanceof Events\EntryResolved
                        ? [fn (Events\EntryResolved $e): object => throw $this->boom]
                        : [];
                }
            };
            $recorder = new RecordingDispatcher($provider);

            $c = new Container([], $recorder);
            $c->set('foo', function () use (&$calls) {
                $calls++;

                return new stdClass;
            });

            $error = null;

            try {
                $c->get('foo');
            } catch (Throwable $e) {
                $error = $e;
            }

            // The listener's exception propagates untouched (user code) …
            expect($error)->toBe($boom);

            $resolved = $recorder->eventsFor(Events\EntryResolved::class);
            expect($resolved)->toHaveLength(1);
            expect($resolved[0]->id)->toBe('foo');

            // … but the cache write already happened: the next get() is a
            // hit, the factory does not re-run, the event is not replayed.
            $cached = $c->get('foo');

            expect($cached)->toBeAnInstanceOf(stdClass::class);
            expect($calls)->toBe(1);
            expect($recorder->eventsFor(Events\EntryResolved::class))->toHaveLength(1);
        });

        it('should cache non-object results without dispatching', function () {
            $this->c->set('void', fn () => null);

            expect($this->c->get('void'))->toBeNull();
            expect($this->c->get('void'))->toBeNull();
            expect($this->recorder->eventsFor(Events\EntryResolved::class))->toHaveLength(0);
        });

        it('should dispatch a single EntryResolved carrying the target id for aliases', function () {
            $this->c->set('impl', fn () => new stdClass);
            $this->c->set('alias', 'impl');

            $value = $this->c->get('alias');

            $resolved = $this->recorder->eventsFor(Events\EntryResolved::class);

            expect($resolved)->toHaveLength(1);
            expect($resolved[0]->id)->toBe('impl');
            expect($resolved[0]->instance)->toBe($value);
        });

        it('should throw NotFoundException naming an id that is absent', function () {
            $error = null;

            try {
                $this->c->get('missing');
            } catch (Container\NotFoundException $e) {
                $error = $e;
            }

            expect($error)->toBeAnInstanceOf(Container\NotFoundException::class);
            expect($error->getName())->toBe('missing');
            expect($error->getMessage())->toBe('Container entry "missing" not found.');
            expect($this->c->has('missing'))->toBeFalsy();
        });

        it('should surface a missing auto-wired dependency as NotFoundException naming that id', function () {
            $this->c->set('svc', fn (Dummy $dummy) => $dummy);

            expect(fn () => $this->c->get('svc'))->toThrow(new Container\NotFoundException('Stubs\Dummy'));
            expect($this->c->has('Stubs\Dummy'))->toBeFalsy();
        });

        it('should guard against circular references while building', function () {
            $c = $this->c;
            $c->set('a', function () use ($c) {
                return $c->get('a');
            });

            expect(fn () => $c->get('a'))->toThrow(new Container\ResolutionException(
                'Failed to resolve "a": circular reference while building.'
            ));
        });
    });

    context('::set', function () {
        it('should classify factories into the matching Entry class', function () {
            $c = $this->c;
            $invokable = new CallableClass(new Dummy);

            $c->set('closure', fn () => null);
            $c->set('invokable', $invokable);
            $c->set('function', 'strlen');
            $c->set('factory', new class implements EntryFactory
            {
                public function create(ContainerInterface $container): object
                {
                    return new stdClass;
                }
            });
            $c->set('pair-string', SomeClass::class.'::handle');
            $c->set('pair-array', [SomeClass::class, 'handle']);
            $c->set('class', InstantiableClass::class);
            $c->set(CertainInterface::class, SomeClass::class);
            $c->set('alias', CertainInterface::class);

            $entries = [];

            foreach ($this->recorder->eventsFor(Events\EntryRegistered::class) as $event) {
                $entries[$event->entry->id] = $event->entry;
            }

            expect($entries['closure'])->toBeAnInstanceOf(Entry\CallableEntry::class);
            expect($entries['invokable'])->toBeAnInstanceOf(Entry\CallableEntry::class);
            expect($entries['invokable']->factory)->toBe($invokable);
            expect($entries['function'])->toBeAnInstanceOf(Entry\CallableEntry::class);
            expect($entries['factory'])->toBeAnInstanceOf(Entry\FactoryEntry::class);
            expect($entries['pair-string'])->toBeAnInstanceOf(Entry\MethodPairEntry::class);
            expect($entries['pair-array'])->toBeAnInstanceOf(Entry\MethodPairEntry::class);
            expect($entries['class'])->toBeAnInstanceOf(Entry\ClassNameEntry::class);
            expect($entries['alias'])->toBeAnInstanceOf(Entry\AliasEntry::class);
        });

        it('should route non-buildable symbols to an alias when the target pre-exists, reject otherwise', function () {
            $c = $this->c;
            $c->set('dummy', Dummy::class);

            expect(fn () => $c->set('iface', CertainInterface::class))->toThrow(new Container\InvalidArgumentException(
                'Cannot register entry "iface": "Stubs\CertainInterface" is neither a registered entry, an instantiable class, nor a function.'
            ));

            expect(fn () => $c->set('abs', AbstractFoo::class))->toThrow(new Container\InvalidArgumentException(
                'Cannot register entry "abs": "Stubs\AbstractFoo" is neither a registered entry, an instantiable class, nor a function.'
            ));

            expect(fn () => $c->set('trait', 'Stubs\RequireDummy'))->toThrow(new Container\InvalidArgumentException(
                'Cannot register entry "trait": "Stubs\RequireDummy" is neither a registered entry, an instantiable class, nor a function.'
            ));

            $c->set(CertainInterface::class, SomeClass::class);
            $c->set(AbstractFoo::class, ConcreteBar::class);
            $c->set('iface-alias', CertainInterface::class);
            $c->set('abstract-alias', AbstractFoo::class);

            expect($c->get('iface-alias'))->toBeAnInstanceOf(SomeClass::class);
            expect($c->get('abstract-alias'))->toBeAnInstanceOf(ConcreteBar::class);
        });

        it('should reject invalid factories with the shared validation messages', function () {
            $c = $this->c;

            $reject = function (mixed $factory, string $message) use ($c): void {
                expect(fn () => $c->set('bad', $factory))->toThrow(
                    new Container\InvalidArgumentException($message)
                );
            };

            // Plain objects.
            $reject(new stdClass, 'Cannot register entry "bad": plain object stdClass is not a factory — register instances as "fn () => $instance" or "new EntryFactory(...)"');

            // Anything else.
            $reject(42, 'Cannot register entry "bad": invalid factory of type int');
            $reject(null, 'Cannot register entry "bad": invalid factory of type null');

            // Pair shape and contents (messages shared with the child specs).
            $reject(['only-one'], 'Cannot register entry "bad": method pair must contain exactly two elements [class, method].');
            $reject([['nope'], 'handle'], 'Cannot register entry "bad": class "array" does not exist.');
            // An object class slot stays a method pair: it unwraps to its class,
            // so the method beside it is what gets validated.
            $reject([new stdClass, 'handle'], 'Cannot register entry "bad": method "stdClass::handle()" does not exist.');
            $reject([SomeClass::class, 'missing'], 'Cannot register entry "bad": method "Stubs\SomeClass::missing()" does not exist.');
            $reject([MultiParamStub::class, 'hidden'], 'Cannot register entry "bad": method "Stubs\MultiParamStub::hidden()" is not public.');

            // By-reference constructor parameter.
            $reject(ByRefStub::class, 'Cannot register entry "bad": by-reference parameter $value is not allowed.');

            // nothing was stored by any of the failures.
            expect($c->has('bad'))->toBeFalsy();
        });

        it('should throw on duplicate registration of a user entry', function () {
            $this->c->set('std', stdClass::class);

            expect(fn () => $this->c->set('std', fn () => null))->toThrow(
                new Container\InvalidArgumentException('Cannot register entry "std": already registered.')
            );
        });

        it('should permit replacing an infrastructure (auto) entry', function () {
            $c = $this->c;

            $c->set(ContainerInterface::class, fn (): ContainerInterface => $c);

            $registered = $this->recorder->eventsFor(Events\EntryRegistered::class);

            expect($registered)->toHaveLength(1);
            expect($registered[0]->entry->auto)->toBeFalsy();
            expect($c->get(ContainerInterface::class))->toBe($c);
        });

        it('should register lazily and dispatch EntryRegistered with the entry payload', function () {
            $factory = function (): void {
                throw new RuntimeException('must not run at registration');
            };

            $this->c->set('lazy', $factory);

            $registered = $this->recorder->eventsFor(Events\EntryRegistered::class);

            expect($registered)->toHaveLength(1);
            expect($registered[0]->entry->id)->toBe('lazy');
            expect($registered[0]->entry->factory)->toBe($factory);
            expect($registered[0]->entry->isBuilt())->toBeFalsy();
            expect($this->c->has('lazy'))->toBeTruthy();
        });

        it('should not resolve anything at registration time', function () {
            $this->c->set('a', [SomeClass::class, 'handle']);

            expect($this->c->has('a'))->toBeTruthy();
            expect(fn () => $this->c->get('a'))->toThrow(
                new Container\NotFoundException('Stubs\AbstractFoo')
            );
        });

        it('should register an instance through the EntryFactory door', function () {
            $instance = new stdClass;

            $this->c->set('instance', new class($instance) implements EntryFactory
            {
                public function __construct(private object $instance) {}

                public function create(ContainerInterface $container): object
                {
                    return $this->instance;
                }
            });

            expect($this->c->get('instance'))->toBe($instance);
        });
    });

    context('::make', function () {
        beforeEach(function () {
            $this->c->set('dummy', Dummy::class);
            $this->c->set(AbstractFoo::class, ConcreteBar::class);
        });

        it('should make a fresh value from a registered id — never cached, no events', function () {
            $this->c->set('svc', fn () => new stdClass);

            $made = $this->c->make('svc');

            expect($made)->toBeAnInstanceOf(stdClass::class);
            expect($this->recorder->eventsFor(Events\EntryResolved::class))->toHaveLength(0);

            $got = $this->c->get('svc');

            expect($got)->not->toBe($made);
            expect($this->c->make('svc'))->not->toBe($made);
        });

        it('should make a fresh resolver without touching the singleton cache', function () {
            $fresh = $this->c->make(ResolverInterface::class);

            expect($fresh)->toBeAnInstanceOf(Resolver::class);
            expect($fresh)->not->toBe($this->c->get(ResolverInterface::class));
        });

        it('should prefer a registered id over class or function look-alikes', function () {
            $this->c->set(stdClass::class, fn () => 'registered');

            expect($this->c->make(stdClass::class))->toBe('registered');
        });

        it('should apply decorators on the registered-id path without touching the cache', function () {
            $c = $this->c;
            $c->set(CouldExtends::class, CouldExtends::class);

            $applied = 0;
            $c->extend(CouldExtends::class, function (CouldExtends $entry) use (&$applied): CouldExtends {
                $applied++;

                return $entry;
            });

            $made = $c->make(CouldExtends::class);

            expect($applied)->toBe(1);
            expect($made)->toBeAnInstanceOf(CouldExtends::class);

            $got = $c->get(CouldExtends::class);

            expect($got)->not->toBe($made);
            expect($applied)->toBe(2);
        });

        it('should unwrap aliases, running target decorators then alias decorators', function () {
            $c = $this->c;
            // A class-string registers as a class entry — an
            // alias needs a non-buildable target id.
            $c->set('target', CouldExtends::class);
            $c->set('alias', 'target');

            $order = [];
            $c->extend('target', function (CouldExtends $entry) use (&$order): CouldExtends {
                $order[] = 'target';

                return $entry;
            });
            $c->extend('alias', function (CouldExtends $entry) use (&$order): CouldExtends {
                $order[] = 'alias';

                return $entry;
            });

            $made = $c->make('alias');

            expect($made)->toBeAnInstanceOf(CouldExtends::class);
            expect($order)->toBe(['target', 'alias']);
        });

        it('should build an unregistered instantiable class-string transiently', function () {
            expect($this->c->has(InstantiableClass::class))->toBeFalsy();

            $one = $this->c->make(InstantiableClass::class);
            $two = $this->c->make(InstantiableClass::class);

            expect($one)->toBeAnInstanceOf(InstantiableClass::class);
            expect($one)->not->toBe($two);
            expect($this->c->has(InstantiableClass::class))->toBeFalsy();
        });

        it('should feed $args to the constructor of a transient class-string', function () {
            $dep = new ConcreteBar(new Dummy);

            expect($this->c->has(VariadicStub::class))->toBeFalsy();

            $made = $this->c->make(VariadicStub::class, [$dep]);

            expect($made)->toBeAnInstanceOf(VariadicStub::class);
            expect($made->foo)->toBe($dep);

            // Row 2 stays transient: never registered, never cached, no events.
            expect($this->c->has(VariadicStub::class))->toBeFalsy();
            expect($this->recorder->eventsFor(Events\EntryResolved::class))->toHaveLength(0);
        });

        it('should accept every callable shape', function () {
            $c = $this->c;
            class_exists(Dummy::class); // loads Stubs\dummyLorem

            // Closure.
            expect($c->make(fn (string $value): string => $value, ['value']))->toBe('value');

            // Invokable object — invoked, not returned.
            expect($c->make(new CallableClass($c->get('dummy'))))->toBeAnInstanceOf(AbstractFoo::class);

            // Function-name string.
            expect($c->make('Stubs\dummyLorem'))->toBe('lorem');

            // Class::method string.
            expect($c->make(SomeClass::class.'::shouldCalled', ['value']))->toBe('value');

            // Pair with class-string slot.
            expect($c->make([SomeClass::class, 'shouldCalled'], ['value']))->toBe('value');

            // Pair with object slot.
            expect($c->make([new SomeClass, 'shouldCalled'], ['value']))->toBe('value');
        });

        it('should inject the container directly into make() results without events', function () {
            $instance = $this->c->make(HasContainerClass::class);

            expect($instance->getContainer())->toBe($this->c);
            expect($this->recorder->eventsFor(Events\EntryResolved::class))->toHaveLength(0);
        });

        it('should throw a native TypeError for a non-array $args', function () {
            expect(fn () => $this->c->make(SomeClass::class, 'not-an-array'))->toThrow(new TypeError);
        });

        it('should reject inputs outside the four accepted families', function () {
            $c = new Container;

            expect(fn () => $c->make(new stdClass))->toThrow(new Container\InvalidArgumentException(
                'Cannot make from "stdClass": plain object has no __invoke — make() accepts a registered entry id, an instantiable class-string, or a callable; pass "fn () => …" instead.'
            ));

            expect(fn () => $c->make('not-registered'))->toThrow(new Container\InvalidArgumentException(
                'Cannot make from "not-registered": make() accepts a registered entry id, an instantiable class-string, or a callable.'
            ));

            expect(fn () => $c->make(CertainInterface::class))->toThrow(new Container\InvalidArgumentException(
                'Cannot make from "Stubs\CertainInterface": make() accepts a registered entry id, an instantiable class-string, or a callable.'
            ));

            expect(fn () => $c->make([]))->toThrow(new Container\InvalidArgumentException(
                'Cannot make from "array": make() accepts a registered entry id, an instantiable class-string, or a callable.'
            ));
        });
    });

    context('::extend', function () {
        beforeEach(function () {
            $this->c->set('dummy', Dummy::class);
            $this->c->set(CouldExtends::class, CouldExtends::class);
        });

        it('should throw NotFoundException for an absent id', function () {
            expect(fn () => $this->c->extend('missing', fn (object $entry): object => $entry))->toThrow(
                new Container\NotFoundException('missing')
            );
        });

        it('should reject a non-derivable extension target', function () {
            $this->c->set('cb', fn () => null);
            $this->c->set('str', fn (): string => 'value');

            expect(fn () => $this->c->extend('cb', fn (object $entry): object => $entry))->toThrow(
                new Container\InvalidArgumentException(
                    'Cannot extend entry "cb": extension target type is not derivable.'
                )
            );

            expect(fn () => $this->c->extend('str', fn (object $entry): object => $entry))->toThrow(
                new Container\InvalidArgumentException(
                    'Cannot extend entry "str": extension target type is not derivable.'
                )
            );
        });

        it('should reject callbacks without an explicit single class return type', function () {
            $id = CouldExtends::class;

            expect(fn () => $this->c->extend($id, fn ($entry) => $entry))->toThrow(
                new Container\InvalidArgumentException(
                    'Cannot extend entry "Stubs\CouldExtends": callback must declare an explicit, non-union, named return type.'
                )
            );

            expect(fn () => $this->c->extend($id, fn ($entry): mixed => $entry))->toThrow(
                new Container\InvalidArgumentException(
                    'Cannot extend entry "Stubs\CouldExtends": callback must declare an explicit, non-union, named return type.'
                )
            );

            expect(fn () => $this->c->extend($id, fn ($entry): CouldExtends|stdClass => $entry))->toThrow(
                new Container\InvalidArgumentException(
                    'Cannot extend entry "Stubs\CouldExtends": callback must declare an explicit, non-union, named return type.'
                )
            );
        });

        it('should reject callbacks whose return type is not the target type', function () {
            expect(fn () => $this->c->extend(CouldExtends::class, fn ($entry): stdClass => $entry))->toThrow(
                new Container\InvalidArgumentException(
                    'Cannot extend entry "Stubs\CouldExtends": callback must return "Stubs\CouldExtends"'
                )
            );
        });

        it('should apply a pending decorator on the first build only', function () {
            $applied = 0;
            $this->c->extend(CouldExtends::class, function (CouldExtends $entry) use (&$applied): CouldExtends {
                $applied++;

                return $entry;
            });

            expect($applied)->toBe(0);

            $got = $this->c->get(CouldExtends::class);

            expect($applied)->toBe(1);
            expect($this->c->get(CouldExtends::class))->toBe($got);
            expect($applied)->toBe(1);
        });

        it('should apply an already-built entry immediately and return the container for chaining', function () {
            $c = $this->c;
            $c->set(SomeClass::class, SomeClass::class);
            $got = $c->get(CouldExtends::class);

            expect($got->dummy)->toBe($c->get('dummy'));

            $extended = $c->extend(CouldExtends::class, function (CouldExtends $entry, SomeClass $other): CouldExtends {
                // The second parameter auto-wires from the container — continuity
                // with the old extend() → make($callback, [$entry]) semantics.
                $entry->dummy = $other;

                return $entry;
            });

            expect($extended)->toBe($c);
            expect($got->dummy)->toBe($c->get(SomeClass::class));
            expect($c->get(CouldExtends::class))->toBe($got);
            expect($got->dummy)->toBe($c->get(SomeClass::class));
        });

        it('should leave the cached value and the decorator list untouched when a decorator throws', function () {
            $c = $this->c;
            $got = $c->get(CouldExtends::class);

            expect(fn () => $c->extend(CouldExtends::class, function (CouldExtends $entry): CouldExtends {
                throw new RuntimeException('boom');
            }))->toThrow(new RuntimeException('boom'));

            // The cached value stands.
            expect($c->get(CouldExtends::class))->toBe($got);

            // Nothing was appended: a fresh rebuild does not re-run the failure.
            $clone = clone $c;
            expect($clone->get(CouldExtends::class))->toBeAnInstanceOf(CouldExtends::class);
        });
    });

    context('clone', function () {
        it('should reset singleton caches without disturbing the original', function () {
            $c = $this->c;
            $c->set('svc', fn () => new stdClass);

            $before = $c->get('svc');
            $clone = clone $c;
            $after = $clone->get('svc');

            expect($after)->not->toBe($before);
            expect($c->get('svc'))->toBe($before);
        });

        it('should copy registrations — later changes do not cross over', function () {
            $c = $this->c;
            $c->set('before', fn () => 'before');

            $clone = clone $c;

            $c->set('on-original', fn () => 'original');
            $clone->set('on-clone', fn () => 'clone');

            expect($clone->has('before'))->toBeTruthy();
            expect($clone->has('on-original'))->toBeFalsy();
            expect($c->has('on-clone'))->toBeFalsy();
            expect($c->has('on-original'))->toBeTruthy();
        });

        it('should re-point the self-referential auto defaults to the clone', function () {
            $clone = clone $this->c;

            expect($clone->get(Container::class))->toBe($clone);
            expect($clone->get(ContainerInterface::class))->toBe($clone);
            expect($this->c->get(Container::class))->toBe($this->c);
            expect($this->c->get(ContainerInterface::class))->toBe($this->c);
        });

        it('should bind the clone — not the original — into instances resolved from it', function () {
            $c = new Container;
            $c->set(HasContainerClass::class, HasContainerClass::class);

            $clone = clone $c;

            expect($clone->get(HasContainerClass::class)->getContainer())->toBe($clone);
            expect($c->get(HasContainerClass::class)->getContainer())->toBe($c);
        });

        it('should keep a constructor-provided dispatcher captured in the default factory', function () {
            $c = new Container([], $this->recorder);

            $clone = clone $c;

            expect($clone->getEventDispatcher())->toBe($this->recorder);
        });

        it('should leave user-replaced infrastructure entries exactly as registered', function () {
            $c = new Container;
            $c->setEventDispatcher($this->recorder);

            $clone = clone $c;

            expect($clone->getEventDispatcher())->toBe($this->recorder);
        });

        it('should carry pending decorators over to the clone with a reset cache', function () {
            $c = $this->c;
            $c->set('dummy', Dummy::class);
            $c->set(CouldExtends::class, CouldExtends::class);

            $runs = 0;
            $c->extend(CouldExtends::class, function (CouldExtends $entry) use (&$runs): CouldExtends {
                $runs++;

                return $entry;
            });

            $c->get(CouldExtends::class);
            expect($runs)->toBe(1);

            $clone = clone $c;
            $cloned = $clone->get(CouldExtends::class);

            expect($runs)->toBe(2);
            expect($cloned)->not->toBe($c->get(CouldExtends::class));
        });

        it('should reset the shared handler so the clone rides its own resolver', function () {
            $c = $this->c;
            $c->set(Dummy::class, Dummy::class);
            $c->set('svc', fn (Dummy $dummy) => $dummy);

            $original = $c->get('svc');   // the original's shared handler is built here

            $clone = clone $c;
            $fresh = $clone->get('svc');

            // A still-shared handler resolves Dummy through the ORIGINAL
            // container and would hand back the original's cached instance.
            expect($fresh)->not->toBe($original);
            expect($fresh)->toBe($clone->get(Dummy::class));
            expect($original)->toBe($c->get(Dummy::class));
        });
    });

    context('boundary', function () {
        it('should rethrow a genuine NotFoundException raw — the deepest missing id wins (rule 1)', function () {
            $c = $this->c;
            $c->set('svc', fn (Dummy $dummy) => $dummy);

            $error = null;

            try {
                $c->get('svc');
            } catch (Container\NotFoundException $e) {
                $error = $e;
            }

            expect($error)->toBeAnInstanceOf(Container\NotFoundException::class);
            expect($error->getName())->toBe('Stubs\Dummy');
            expect($c->has('Stubs\Dummy'))->toBeFalsy();
        });

        it('should not double-wrap an existing ResolutionException (rule 2)', function () {
            $c = $this->c;
            $c->set('a', function () use ($c) {
                return $c->get('a');
            });

            expect(fn () => $c->get('a'))->toThrow(new Container\ResolutionException(
                'Failed to resolve "a": circular reference while building.'
            ));
        });

        it('should wrap package failures as ResolutionException for get() and make() (rule 3)', function () {
            $this->c->set('counter', fn (int $count) => $count);

            expect(fn () => $this->c->get('counter'))->toThrow(new Container\ResolutionException(
                'Failed to resolve "counter": {closure}(): Argument #1 ($count) is not resolvable'
            ));

            // A build failure never wears the NotFoundException label.
            expect($this->c->has('counter'))->toBeTruthy();

            expect(fn () => $this->c->make([SomeClass::class, 'nope']))->toThrow(
                new Container\ResolutionException(
                    'Failed to resolve "Stubs\SomeClass::nope": Method Stubs\SomeClass::nope() does not exist'
                )
            );
        });

        it('should rethrow user-code throwables untouched for get() and make() (rule 4)', function () {
            $c = $this->c;
            $boom = new RuntimeException('user boom');
            $c->set('bad', function () use ($boom) {
                throw $boom;
            });

            $error = null;

            try {
                $c->get('bad');
            } catch (Throwable $e) {
                $error = $e;
            }

            expect($error)->toBe($boom);

            $error = null;

            try {
                $c->make('bad');
            } catch (Throwable $e) {
                $error = $e;
            }

            expect($error)->toBe($boom);

            // make()'s own input rejection passes the boundary untouched.
            expect(fn () => $c->make('nope'))->toThrow(new Container\InvalidArgumentException(
                'Cannot make from "nope": make() accepts a registered entry id, an instantiable class-string, or a callable.'
            ));
        });

        it('should catch make() re-entering its own registered entry as circular', function () {
            $c = $this->c;
            $c->set('circular', function () use ($c) {
                return $c->make('circular');
            });

            expect(fn () => $c->make('circular'))->toThrow(new Container\ResolutionException(
                'Failed to resolve "circular": circular reference while building.'
            ));
        });

        it('should unwrap a nested NotFoundException raised inside make() (rule 1)', function () {
            expect(fn () => $this->c->make(['NotRegistered', 'method']))->toThrow(
                new Container\NotFoundException('NotRegistered')
            );

            expect($this->c->has('NotRegistered'))->toBeFalsy();
        });
    });

    context('wiring', function () {
        it('should insert the infrastructure defaults directly, without events', function () {
            $provider = new Events\ListenerProvider;
            $recorder = new RecordingDispatcher($provider);
            $c = new Container(['user' => fn () => 'value'], $recorder);
            $provider->setContainer($c);

            $registered = $recorder->eventsFor(Events\EntryRegistered::class);

            expect($registered)->toHaveLength(1);
            expect($registered[0]->entry->id)->toBe('user');

            // Building every default dispatches nothing (auto rule).
            expect($c->get(Container::class))->toBe($c);
            expect($c->get(ContainerInterface::class))->toBe($c);
            expect($c->get(ResolverInterface::class))->toBeAnInstanceOf(Resolver::class);
            expect($c->getEventDispatcher())->toBe($recorder);
            expect($recorder->eventsFor(Events\EntryResolved::class))->toHaveLength(0);
            expect($recorder->eventsFor(Events\EntryRegistered::class))->toHaveLength(1);
        });

        it('should expose the effective resolver through getResolver()', function () {
            $custom = new SpyResolver(new Resolver($this->c));

            $this->c->set(ResolverInterface::class, fn (): ResolverInterface => $custom);

            expect($this->c->getResolver())->toBe($custom);
            expect($this->c->getResolver())->toBe($this->c->get(ResolverInterface::class));

            // The resolver-replacement EntryResolved fired while the
            // EventDispatcherInterface entry was mid-build (its build pulls
            // the shared handler, which pulls this resolver) — it was queued
            // and must be delivered by the FIFO flush, before the outer
            // EntryRegistered dispatch completes.
            $resolved = $this->recorder->eventsFor(Events\EntryResolved::class);
            $registered = $this->recorder->eventsFor(Events\EntryRegistered::class);

            expect($resolved)->toHaveLength(1);
            expect($resolved[0]->id)->toBe(ResolverInterface::class);
            expect($resolved[0]->instance)->toBe($custom);
            expect($registered)->toHaveLength(1);
            expect($registered[0]->entry->id)->toBe(ResolverInterface::class);
            expect($this->recorder->events[0])->toBe($resolved[0]);
            expect($this->recorder->events[1])->toBe($registered[0]);
        });

        it('should provide a default internal dispatcher lazily when none is given', function () {
            $c = new Container;

            expect($c->getEventDispatcher())->toBeAnInstanceOf(Events\Dispatcher::class);
            expect($c->getEventDispatcher())->toBe($c->getEventDispatcher());
        });

        it('should force-replace the dispatcher and fire EntryRegistered', function () {
            $replacement = new RecordingDispatcher($this->provider);

            $this->c->setEventDispatcher($replacement);

            expect($this->c->getEventDispatcher())->toBe($replacement);

            $registered = $replacement->eventsFor(Events\EntryRegistered::class);
            expect($registered)->toHaveLength(1);
            expect($registered[0]->entry->id)->toBe(EventDispatcherInterface::class);

            $replacement->reset();
            $this->c->set('svc', fn () => new stdClass);
            $this->c->get('svc');

            expect($replacement->eventsFor(Events\EntryRegistered::class))->toHaveLength(1);

            $resolved = $replacement->eventsFor(Events\EntryResolved::class);
            expect($resolved)->toHaveLength(1);
            expect($resolved[0]->id)->toBe('svc');
        });

        it('should stop event propagation on a stopped stoppable event', function () {
            $provider = new class implements ListenerProviderInterface
            {
                public $count = 0;

                public function getListenersForEvent(object $event): iterable
                {
                    return [
                        function ($e) {
                            $this->count++;
                            $e->stop = true;
                        },
                        function ($e) {
                            $this->count++;
                        },
                    ];
                }
            };

            $dispatcher = new Events\Dispatcher($this->c, $provider);

            $stoppable = new class implements StoppableEventInterface
            {
                public $stop = false;

                public function isPropagationStopped(): bool
                {
                    return $this->stop;
                }
            };

            $dispatcher->dispatch($stoppable);

            expect($provider->count)->toBe(1);
        });
    });
});
