<?php

declare(strict_types=1);

use Projek\Callable\Handler;
use Projek\Callable\Resolver;
use Projek\Callable\ResolverInterface;
use Projek\Container\Entry\ClassNameEntry;
use Projek\Container\EntryCollector;
use Projek\Container\InvalidArgumentException;
use Projek\Container\NotFoundException;
use Stubs\AbstractFoo;
use Stubs\ByRefStub;
use Stubs\CertainInterface;
use Stubs\ConcreteBar;
use Stubs\ConstructorCounter;
use Stubs\DefaultParamsStub;
use Stubs\Dummy;
use Stubs\MultiParamStub;
use Stubs\ServiceProvider;
use Stubs\SpyResolver;
use Stubs\StubContainer;
use Stubs\VariadicStub;

use function Kahlan\describe;
use function Kahlan\expect;
use function Kahlan\it;

describe(ClassNameEntry::class, function () {
    /**
     * Build a container wired with a recording resolver (per-build fetch).
     */
    $wired = function (): array {
        $container = new StubContainer([]);
        $spy = new SpyResolver(new Resolver($container));
        $container->entries[ResolverInterface::class] = $spy;

        return [$container, $spy];
    };

    it('recognises an instantiable class-string with a constructor', function () {
        $entry = new ClassNameEntry('provider', ServiceProvider::class);

        expect($entry->id)->toBe('provider');
        expect($entry->factory)->toBe('Stubs\ServiceProvider');
        expect($entry->parameters)->toBe(['abs' => 'Stubs\AbstractFoo']);
        expect($entry->returnType)->toBe('Stubs\ServiceProvider');
        expect($entry->factory)->toBe('Stubs\ServiceProvider');
    });

    it('recognises a class-string without a constructor', function () {
        $entry = new ClassNameEntry('dummy', Dummy::class);

        expect($entry->parameters)->toBe([]);
        expect($entry->returnType)->toBe('Stubs\Dummy');
    });

    it('defensively rejects a non-instantiable class-string', function () {
        expect(fn () => new ClassNameEntry('bad', AbstractFoo::class))->toThrow(
            InvalidArgumentException::notInstantiable('bad', AbstractFoo::class)
        );

        expect(fn () => new ClassNameEntry('bad', CertainInterface::class))->toThrow(
            InvalidArgumentException::notInstantiable('bad', CertainInterface::class)
        );
    });

    it('defensively rejects a constructor with by-reference parameters', function () {
        expect(fn () => new ClassNameEntry('bad', ByRefStub::class))->toThrow(
            InvalidArgumentException::byReferenceParam('bad', 'value')
        );
    });

    it('delegates empty-args construction to the container-supplied resolver', function () {
        $container = new StubContainer([AbstractFoo::class => new ConcreteBar(null)]);
        $spy = new SpyResolver(new Resolver($container));
        $container->entries[ResolverInterface::class] = $spy;
        $handler = new Handler(new StubContainer([]));
        $entry = new ClassNameEntry('provider', ServiceProvider::class);

        $instance = $entry->build($handler, $container);

        expect($instance)->toBeAnInstanceOf(ServiceProvider::class);
        expect($spy->instances)->toBe(['Stubs\ServiceProvider']);
    });

    it('constructs the class once per build when arguments are empty', function () {
        $container = new StubContainer([]);
        $container->entries[ResolverInterface::class] = new Resolver($container);
        $handler = new Handler(new StubContainer([]));
        $entry = new ClassNameEntry('counter', ConstructorCounter::class);

        ConstructorCounter::$count = 0;
        $instance = $entry->build($handler, $container);

        expect($instance)->toBeAnInstanceOf(ConstructorCounter::class);
        expect(ConstructorCounter::$count)->toBe(1);
    });

    it('fetches the resolver through the container on every build', function () {
        $container = new StubContainer([AbstractFoo::class => new ConcreteBar(null)]);
        $handler = new Handler(new StubContainer([]));
        $entry = new ClassNameEntry('provider', ServiceProvider::class);

        expect(fn () => $entry->build($handler, $container))->toThrow(
            new NotFoundException('Projek\Callable\ResolverInterface')
        );
    });

    it('binds positional arguments by order', function () use ($wired) {
        [$container] = ($wired)();
        $container->entries[AbstractFoo::class] = new ConcreteBar(null);
        $positional = new ConcreteBar(null);
        $handler = new Handler(new StubContainer([]));
        $entry = new ClassNameEntry('multi', MultiParamStub::class);

        $instance = $entry->build($handler, $container, [$positional, 'alice']);

        expect($instance->foo)->toBe($positional);
        expect($instance->name)->toBe('alice');
    });

    it('binds named arguments and falls back to resolveParameter() for the rest', function () use ($wired) {
        [$container, $spy] = ($wired)();
        $fromContainer = new ConcreteBar(null);
        $container->entries[AbstractFoo::class] = $fromContainer;
        $handler = new Handler(new StubContainer([]));
        $entry = new ClassNameEntry('multi', MultiParamStub::class);

        $instance = $entry->build($handler, $container, ['name' => 'alice']);

        expect($instance->name)->toBe('alice');
        expect($instance->foo)->toBe($fromContainer);
        expect($spy->parameters)->toBe(['foo']);
    });

    it('raises a native Error on unknown named arguments', function () use ($wired) {
        [$container] = ($wired)();
        $handler = new Handler(new StubContainer([]));
        $entry = new ClassNameEntry('multi', MultiParamStub::class);

        expect(fn () => $entry->build($handler, $container, ['nope' => 'value']))->toThrow(
            new Error('Unknown named parameter $nope')
        );
    });

    it('splices leftover arguments into a trailing variadic', function () use ($wired) {
        [$container] = ($wired)();
        $foo = new ConcreteBar(null);
        $handler = new Handler(new StubContainer([]));
        $entry = new ClassNameEntry('variadic', VariadicStub::class);

        $instance = $entry->build($handler, $container, [$foo, 'a', 'b']);

        expect($instance->foo)->toBe($foo);
        expect($instance->extras)->toBe(['a', 'b']);

        $named = $entry->build($handler, $container, [$foo, 'extra' => 'x']);

        expect($named->extras)->toBe(['extra' => 'x']);
    });

    it('falls back to declared defaults for unprovided parameters', function () use ($wired) {
        [$container] = ($wired)();
        $foo = new ConcreteBar(null);
        $handler = new Handler(new StubContainer([]));
        $entry = new ClassNameEntry('optional', DefaultParamsStub::class);

        $instance = $entry->build($handler, $container, ['foo' => $foo]);

        expect($instance->foo)->toBe($foo);
        expect($instance->name)->toBe('default');

        $named = $entry->build($handler, $container, ['name' => 'given']);

        expect($named->name)->toBe('given');
        expect($named->foo)->toBeNull();
    });

    it('constructs a constructorless class natively when arguments are given', function () use ($wired) {
        [$container] = ($wired)();
        $handler = new Handler(new StubContainer([]));
        $entry = new ClassNameEntry('dummy', Dummy::class);

        expect($entry->build($handler, $container, ['ignored']))->toBeAnInstanceOf(Dummy::class);
    });

    it('derives the extension target as the class itself', function () {
        $entry = new ClassNameEntry('provider', ServiceProvider::class);

        expect($entry->extensionTarget(new EntryCollector))->toBe('Stubs\ServiceProvider');
    });
});
