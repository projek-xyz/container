<?php

declare(strict_types=1);

use Projek\Callable\Handler;
use Projek\Container\Entry\CallableEntry;
use Projek\Container\EntryCollector;
use Projek\Container\InvalidArgumentException;
use Stubs\AbstractFoo;
use Stubs\ByRefStub;
use Stubs\CallableClass;
use Stubs\CertainInterface;
use Stubs\ConcreteBar;
use Stubs\Dummy;
use Stubs\SomeClass;
use Stubs\StubContainer;

use function Kahlan\describe;
use function Kahlan\expect;
use function Kahlan\it;

describe(CallableEntry::class, function () {
    it('recognises a closure factory and captures its metadata', function () {
        $factory = function (AbstractFoo $foo, ?string $text): SomeClass {
            return new SomeClass;
        };

        $entry = new CallableEntry('closure', $factory, true);

        expect($entry->id)->toBe('closure');
        expect($entry->auto)->toBeTruthy();
        expect($entry->factory)->toBe($factory);
        expect($entry->parameters)->toBe([
            'foo' => 'Stubs\AbstractFoo',
            'text' => '?string',
        ]);
        expect($entry->returnType)->toBe('Stubs\SomeClass');
        expect($entry->getFactory())->toBe($factory);
    });

    it('recognises a function-string factory and captures its metadata', function () {
        // the function lives in Dummy.php — trigger the class autoload first
        \class_exists(Dummy::class);

        $entry = new CallableEntry('fn', 'Stubs\dummyLorem');

        expect($entry->factory)->toBe('Stubs\dummyLorem');
        expect($entry->parameters)->toBe(['foo' => 'Stubs\AbstractFoo']);
        expect($entry->returnType)->toBeNull();
        expect($entry->getFactory())->toBe('Stubs\dummyLorem');
    });

    it('recognises an invokable object and stores it as-is, never re-instantiated', function () {
        $factory = new CallableClass(new Dummy);

        $entry = new CallableEntry('invokable', $factory);

        expect($entry->factory)->toBe($factory);
        expect($entry->parameters)->toBe(['foo' => 'Stubs\AbstractFoo']);
        expect($entry->returnType)->toBeNull();
    });

    it('rejects a closure with by-reference parameters', function () {
        expect(fn () => new CallableEntry('byref', function (&$value) {
            // .
        }))->toThrow(
            new InvalidArgumentException('Cannot register entry "byref": by-reference parameter $value is not allowed.')
        );
    });

    it('rejects a callable object whose __invoke takes by-reference parameters', function () {
        expect(fn () => new CallableEntry('byref', new ByRefStub))->toThrow(
            new InvalidArgumentException('Cannot register entry "byref": by-reference parameter $value is not allowed.')
        );
    });

    it('rejects a plain object without __invoke', function () {
        expect(fn () => new CallableEntry('plain', new stdClass))->toThrow(
            new InvalidArgumentException(
                'Cannot register entry "plain": plain object stdClass is not a factory — register instances as "fn () => $instance" or "new EntryFactory(...)"'
            )
        );
    });

    it('rejects a string that is not a function', function () {
        expect(fn () => new CallableEntry('bad', 'not-a-function'))->toThrow(
            new InvalidArgumentException('Cannot register entry "bad": "not-a-function" is not a function.')
        );
    });

    it('produces by handing the factory and args to the handler', function () {
        $handler = new Handler(new StubContainer([]));
        $container = new StubContainer([]);
        $entry = new CallableEntry('up', fn (string $value): string => \strtoupper($value));

        expect($entry->build($handler, $container, ['hi']))->toBe('HI');
    });

    it('produces from a function string with auto-wired arguments', function () {
        // the function lives in Dummy.php — trigger the class autoload first
        \class_exists(Dummy::class);

        $foo = new ConcreteBar(null);
        $container = new StubContainer([AbstractFoo::class => $foo]);
        $handler = new Handler($container);
        $entry = new CallableEntry('fn', 'Stubs\dummyLorem');

        expect($entry->build($handler, $container))->toBe('lorem');
    });

    it('invokes a callable object as-is', function () {
        $factory = new SomeClass;
        $entry = new CallableEntry('object', $factory);
        $handler = new Handler(new StubContainer([]));

        expect($entry->build($handler, new StubContainer([])))->toBe($factory);
    });

    it('derives the extension target from a named class return type', function () {
        $entry = new CallableEntry('named', fn (): SomeClass => new SomeClass);

        expect($entry->extensionTarget(new EntryCollector))->toBe('Stubs\SomeClass');
    });

    it('derives no extension target without a named class return type', function () {
        $collector = new EntryCollector;

        $none = new CallableEntry('none', fn ($value) => $value);
        $builtin = new CallableEntry('builtin', fn (string $value): string => $value);
        $nullable = new CallableEntry('nullable', fn (string $value): ?string => $value);
        $object = new CallableEntry('object', fn (): object => new stdClass);
        $union = new CallableEntry('union', fn (): string|int => 1);
        $intersection = new CallableEntry('intersection', function (SomeClass $value): AbstractFoo&CertainInterface {
            return $value;
        });

        expect($none->extensionTarget($collector))->toBeNull();
        expect($builtin->extensionTarget($collector))->toBeNull();
        expect($nullable->extensionTarget($collector))->toBeNull();
        expect($object->extensionTarget($collector))->toBeNull();
        expect($union->extensionTarget($collector))->toBeNull();
        expect($intersection->extensionTarget($collector))->toBeNull();
    });
});
