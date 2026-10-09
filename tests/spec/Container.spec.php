<?php

declare(strict_types=1);

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
use Stubs\BuiltinParamStub;
use Stubs\ByRefStub;
use Stubs\CallableClass;
use Stubs\CertainInterface;
use Stubs\ConcreteBar;
use Stubs\ConstructorCounter;
use Stubs\CouldExtends;
use Stubs\Dummy;
use Stubs\HasContainerClass;
use Stubs\InstantiableClass;
use Stubs\MultiParamStub;
use Stubs\RecordingDispatcher;
use Stubs\SomeClass;
use Stubs\StubContainer;
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
                /**
                 * Hold the single listener this provider hands back for EntryResolved events.
                 */
                public function __construct(private mixed $listener) {}

                /**
                 * The recorded listener for EntryResolved events; nothing for any other event.
                 */
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
                /**
                 * Hold the exception the throwing listener raises.
                 */
                public function __construct(private RuntimeException $boom) {}

                /**
                 * An EntryResolved listener that always throws; nothing for any other event.
                 */
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
                /**
                 * Hand back a fresh stdClass, ignoring the container.
                 */
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

            expect(fn () => $c->set('iface', CertainInterface::class))->toThrow(
                Container\InvalidArgumentException::unresolvableString('iface', CertainInterface::class)
            );

            expect(fn () => $c->set('abs', AbstractFoo::class))->toThrow(
                Container\InvalidArgumentException::unresolvableString('abs', AbstractFoo::class)
            );

            expect(fn () => $c->set('trait', 'Stubs\RequireDummy'))->toThrow(
                Container\InvalidArgumentException::unresolvableString('trait', 'Stubs\RequireDummy')
            );

            $c->set(CertainInterface::class, SomeClass::class);
            $c->set(AbstractFoo::class, ConcreteBar::class);
            $c->set('iface-alias', CertainInterface::class);
            $c->set('abstract-alias', AbstractFoo::class);

            expect($c->get('iface-alias'))->toBeAnInstanceOf(SomeClass::class);
            expect($c->get('abstract-alias'))->toBeAnInstanceOf(ConcreteBar::class);
        });

        it('should reject an alias cycle when replacing an auto default entry', function () {
            /** @link https://github.com/projek-xyz/container/pull/94#discussion_r4225874132 */
            expect(fn () => new Container([
                'x' => ContainerInterface::class,
                ContainerInterface::class => 'x',
            ]))->toThrow(
                Container\InvalidArgumentException::unresolvableString(ContainerInterface::class, 'x')
            );
        });

        it('should reject a direct self-alias when replacing an auto default entry', function () {
            /** @link https://github.com/projek-xyz/container/pull/94#discussion_r4225874132 */
            expect(fn () => new Container([
                ContainerInterface::class => ContainerInterface::class,
            ]))->toThrow(
                Container\InvalidArgumentException::unresolvableString(ContainerInterface::class, ContainerInterface::class)
            );
        });

        it('should reject a multi-hop alias cycle when replacing an auto default entry', function () {
            /** @link https://github.com/projek-xyz/container/pull/94#discussion_r4225874132 */
            $c = new Container([
                'a' => ContainerInterface::class,
                'b' => 'a',
            ]);

            expect(fn () => $c->set(ContainerInterface::class, 'b'))->toThrow(
                Container\InvalidArgumentException::unresolvableString(ContainerInterface::class, 'b')
            );
        });

        it('should reject invalid factories with the shared validation messages', function () {
            $c = $this->c;

            $reject = function (mixed $factory, Container\InvalidArgumentException $expected) use ($c): void {
                expect(fn () => $c->set('bad', $factory))->toThrow($expected);
            };

            // Plain objects.
            $reject(new stdClass, Container\InvalidArgumentException::plainObjectNotAFactory('bad', new stdClass));

            // Anything else.
            $reject(42, Container\InvalidArgumentException::invalidFactoryType('bad', 42));
            $reject(null, Container\InvalidArgumentException::invalidFactoryType('bad', null));

            // Pair shape and contents (messages shared with the child specs).
            $reject(['only-one'], Container\InvalidArgumentException::invalidFactoryType('bad', ['only-one']));
            $reject([['nope'], 'handle'], Container\InvalidArgumentException::pairClassNotFound('bad', ['nope']));
            // An object class slot stays a method pair: it unwraps to its class,
            // so the method beside it is what gets validated.
            $reject([new stdClass, 'handle'], Container\InvalidArgumentException::pairMethodNotFound('bad', 'stdClass', 'handle'));
            $reject([SomeClass::class, 'missing'], Container\InvalidArgumentException::pairMethodNotFound('bad', SomeClass::class, 'missing'));
            $reject([MultiParamStub::class, 'hidden'], Container\InvalidArgumentException::pairMethodNotPublic('bad', MultiParamStub::class, 'hidden'));

            // By-reference constructor parameter.
            $reject(ByRefStub::class, Container\InvalidArgumentException::byReferenceParam('bad', 'value'));

            // nothing was stored by any of the failures.
            expect($c->has('bad'))->toBeFalsy();
        });

        it('should throw on duplicate registration of a user entry', function () {
            $this->c->set('std', stdClass::class);

            expect(fn () => $this->c->set('std', fn () => null))->toThrow(
                Container\InvalidArgumentException::alreadyRegistered('std')
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
                /**
                 * Hold the pre-built instance create() hands back.
                 */
                public function __construct(private object $instance) {}

                /**
                 * Hand back the captured instance, ignoring the container.
                 */
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

        it('should make a fresh value from a class without touching the singleton cache', function () {
            $got = $this->c->get('dummy');
            $fresh = $this->c->make(Dummy::class);

            expect($fresh)->toBeAnInstanceOf(Dummy::class);
            expect($fresh)->not->toBe($got);
            expect($this->c->get('dummy'))->toBe($got);
        });

        it('should prefer a registered id over class or function look-alikes', function () {
            $this->c->set(stdClass::class, fn () => 'registered');

            expect($this->c->make(stdClass::class))->toBe('registered');
        });

        it('should construct a class entry once per build when arguments are empty', function () {
            /** @link https://github.com/projek-xyz/container/pull/94#discussion_r4225874142 */
            $c = $this->c;
            $c->set(ConstructorCounter::class, ConstructorCounter::class);

            ConstructorCounter::$count = 0;
            $got = $c->get(ConstructorCounter::class);

            expect($got)->toBeAnInstanceOf(ConstructorCounter::class);
            expect(ConstructorCounter::$count)->toBe(1);

            $made = $c->make(ConstructorCounter::class);
            expect($made)->toBeAnInstanceOf(ConstructorCounter::class);
            expect(ConstructorCounter::$count)->toBe(2);
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

            expect(fn () => $c->make(new stdClass))->toThrow(
                Container\InvalidArgumentException::cannotMakePlainObject('stdClass')
            );

            expect(fn () => $c->make('not-registered'))->toThrow(
                Container\InvalidArgumentException::cannotMakeUnsupported('not-registered')
            );

            expect(fn () => $c->make(CertainInterface::class))->toThrow(
                Container\InvalidArgumentException::cannotMakeUnsupported(CertainInterface::class)
            );

            expect(fn () => $c->make([]))->toThrow(
                Container\InvalidArgumentException::cannotMakeUnsupported('array')
            );
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
                Container\InvalidArgumentException::extensionTargetNotDerivable('cb')
            );

            expect(fn () => $this->c->extend('str', fn (object $entry): object => $entry))->toThrow(
                Container\InvalidArgumentException::extensionTargetNotDerivable('str')
            );
        });

        it('should reject a nullable-class return — nullability survives extraction', function () {
            $this->c->set('nullable', fn (): ?ConcreteBar => null);

            expect(fn () => $this->c->extend('nullable', fn (ConcreteBar $c): ConcreteBar => $c))->toThrow(
                Container\InvalidArgumentException::extensionTargetNotDerivable('nullable')
            );
        });

        it('should reject callbacks without an explicit single class return type', function () {
            $id = CouldExtends::class;

            expect(fn () => $this->c->extend($id, fn ($entry) => $entry))->toThrow(
                Container\InvalidArgumentException::callbackReturnTypeInvalid($id)
            );

            expect(fn () => $this->c->extend($id, fn ($entry): mixed => $entry))->toThrow(
                Container\InvalidArgumentException::callbackReturnTypeInvalid($id)
            );

            expect(fn () => $this->c->extend($id, fn ($entry): CouldExtends|stdClass => $entry))->toThrow(
                Container\InvalidArgumentException::callbackReturnTypeInvalid($id)
            );
        });

        it('should reject callbacks whose return type is not the target type', function () {
            expect(fn () => $this->c->extend(CouldExtends::class, fn ($entry): stdClass => $entry))->toThrow(
                Container\InvalidArgumentException::callbackReturnMismatch(CouldExtends::class, CouldExtends::class)
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

        it('should leave mutations on the cached instance when a decorator throws after mutating', function () {
            /** @link https://github.com/projek-xyz/container/pull/94#discussion_r4225874104 */
            $c = $this->c;
            $got = $c->get(CouldExtends::class);

            expect(fn () => $c->extend(CouldExtends::class, function (CouldExtends $entry): CouldExtends {
                $entry->dummy = new Dummy;

                throw new RuntimeException('boom');
            }))->toThrow(new RuntimeException('boom'));

            // The cached reference is not replaced and prior mutations are not rolled back.
            expect($c->get(CouldExtends::class))->toBe($got);
            expect($c->get(CouldExtends::class)->dummy)->toBe($got->dummy);

            // The failing decorator was not appended: a clone gets a fresh unmodified instance.
            $clone = clone $c;
            expect($clone->get(CouldExtends::class)->dummy)->not->toBe($got->dummy);
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

        it('should clone correctly when auto default entries are replaced with user factory shapes', function () {
            /** @link https://github.com/projek-xyz/container/pull/94#discussion_r4225874119 */
            $c = new Container([], $this->recorder);
            $c->set(Container::class, StubContainer::class);
            $c->set(ContainerInterface::class, static fn (): ContainerInterface => new StubContainer([]));
            $c->set(EventDispatcherInterface::class, static fn (): EventDispatcherInterface => new RecordingDispatcher(new Events\ListenerProvider));

            $runs = 0;
            $c->extend(Container::class, function (StubContainer $entry) use (&$runs): StubContainer {
                $runs++;

                return $entry;
            });

            $c->get(Container::class);
            expect($runs)->toBe(1);

            $clone = clone $c;
            $cloned = $clone->get(Container::class);

            expect($cloned)->toBeAnInstanceOf(StubContainer::class);
            expect($cloned)->not->toBe($c->get(Container::class));
            expect($runs)->toBe(2);
            expect($clone->get(ContainerInterface::class))->toBeAnInstanceOf(StubContainer::class);
            expect($clone->get(EventDispatcherInterface::class))->toBeAnInstanceOf(RecordingDispatcher::class);
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

            // make('counter') wraps package failures on registered-id path through boundary()
            /** @link https://github.com/projek-xyz/container/pull/94#discussion_r4225874138 */
            expect(fn () => $this->c->make('counter'))->toThrow(new Container\ResolutionException(
                'Failed to resolve "counter": {closure}(): Argument #1 ($count) is not resolvable'
            ));

            // make() for transient class-string wraps package failures through boundary()
            expect(fn () => $this->c->make(BuiltinParamStub::class))->toThrow(
                new Container\ResolutionException(
                    'Failed to resolve "Stubs\BuiltinParamStub": Stubs\BuiltinParamStub::__construct(): Argument #1 ($count) is not resolvable'
                )
            );

            expect(fn () => $this->c->make([SomeClass::class, 'nope']))->toThrow(
                new Container\ResolutionException(
                    'Failed to resolve "Stubs\SomeClass::nope": Method Stubs\SomeClass::nope() does not exist'
                )
            );
        });

        it('should clear the building guard when resolution throws so retrying is allowed', function () {
            /** @link https://github.com/projek-xyz/container/pull/94#discussion_r4225874127 */
            $c = $this->c;
            $shouldFail = true;
            $c->set('retryable', function () use (&$shouldFail) {
                if ($shouldFail) {
                    throw new RuntimeException('temporary error');
                }

                return 'recovered';
            });

            expect(fn () => $c->get('retryable'))->toThrow(new RuntimeException('temporary error'));

            $shouldFail = false;
            expect($c->get('retryable'))->toBe('recovered');

            $shouldFail = true;
            $c->set('retryable_make', function () use (&$shouldFail) {
                if ($shouldFail) {
                    throw new RuntimeException('temporary error in make');
                }

                return 'recovered make';
            });

            expect(fn () => $c->make('retryable_make'))->toThrow(new RuntimeException('temporary error in make'));

            $shouldFail = false;
            expect($c->make('retryable_make'))->toBe('recovered make');
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
            expect(fn () => $c->make('nope'))->toThrow(
                Container\InvalidArgumentException::cannotMakeUnsupported('nope')
            );
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
            expect($c->getEventDispatcher())->toBe($recorder);
            expect($recorder->eventsFor(Events\EntryResolved::class))->toHaveLength(0);
            expect($recorder->eventsFor(Events\EntryRegistered::class))->toHaveLength(1);
        });

        it('should not register the resolver as an entry', function () {
            expect($this->c->has(ResolverInterface::class))->toBeFalsy();

            expect(fn () => $this->c->get(ResolverInterface::class))->toThrow(
                new Container\NotFoundException('Projek\Callable\ResolverInterface')
            );
        });

        it('should queue events raised while the dispatcher entry is mid-build and flush them FIFO', function () {
            $recorder = $this->recorder;
            $c = $this->c;
            $c->set('svc', fn () => new stdClass);

            $c->set(EventDispatcherInterface::class, function () use ($c, $recorder) {
                // Registration inside the dispatcher entry's own build: the
                // event must be queued and delivered by the FIFO flush, before
                // the outer EntryRegistered dispatch completes.
                $c->set('late', fn () => 'value');

                return $recorder;
            });

            expect($c->getEventDispatcher())->toBe($recorder);

            $registered = $recorder->eventsFor(Events\EntryRegistered::class);

            expect($registered)->toHaveLength(3);
            expect($registered[0]->entry->id)->toBe('svc');
            expect($registered[1]->entry->id)->toBe('late');
            expect($registered[2]->entry->id)->toBe(EventDispatcherInterface::class);
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

                /**
                 * Two counting listeners; the first flags the event as propagation-stopped.
                 */
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

                /**
                 * Whether a listener has flagged this event as stopped.
                 */
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
