<?php

declare(strict_types=1);

use Projek\Callable\Handler;
use Projek\Container\Entry\MethodPairEntry;
use Projek\Container\EntryCollector;
use Projek\Container\InvalidArgumentException;
use Stubs\AbstractFoo;
use Stubs\ByRefStub;
use Stubs\ConcreteBar;
use Stubs\MultiParamStub;
use Stubs\SomeClass;
use Stubs\StubContainer;

use function Kahlan\describe;
use function Kahlan\expect;
use function Kahlan\it;

describe(MethodPairEntry::class, function () {
    it('recognises a Class::method string and captures its metadata', function () {
        $entry = new MethodPairEntry('handle', 'Stubs\SomeClass::handle');

        expect($entry->id)->toBe('handle');
        expect($entry->factory)->toBe('Stubs\SomeClass::handle');
        expect($entry->parameters)->toBe([
            'dummy' => 'Stubs\AbstractFoo',
            'text' => '?string',
        ]);
        expect($entry->returnType)->toBe('string');
        expect($entry->factory)->toBe('Stubs\SomeClass::handle');
    });

    it('recognises a class-string pair and captures its metadata', function () {
        $factory = [SomeClass::class, 'handle'];

        $entry = new MethodPairEntry('pair', $factory);

        expect($entry->factory)->toBe($factory);
        expect($entry->parameters)->toBe([
            'dummy' => 'Stubs\AbstractFoo',
            'text' => '?string',
        ]);
        expect($entry->returnType)->toBe('string');
        expect($entry->factory)->toBe($factory);
    });

    it('recognises an object pair and reflects the method on its class', function () {
        $factory = [new SomeClass, 'shouldCalled'];

        $entry = new MethodPairEntry('object-pair', $factory);

        expect($entry->factory)->toBe($factory);
        expect($entry->parameters)->toBe(['param' => null]);
        expect($entry->returnType)->toBeNull();
    });

    it('does not reject by-reference method parameters (method pairs have no by-ref rule)', function () {
        $entry = new MethodPairEntry('byref', [ByRefStub::class, 'byRefMethod']);

        expect($entry->factory)->toBe([ByRefStub::class, 'byRefMethod']);
        expect($entry->parameters)->toBe(['value' => 'mixed']);
    });

    it('rejects a string without a method separator', function () {
        expect(fn () => new MethodPairEntry('bad', 'plain'))->toThrow(
            new InvalidArgumentException('Cannot register entry "bad": "plain" is not a "Class::method" string.')
        );
    });

    it('rejects a pair without exactly two elements', function () {
        expect(fn () => new MethodPairEntry('bad', [SomeClass::class]))->toThrow(
            new InvalidArgumentException(
                'Cannot register entry "bad": method pair must contain exactly two elements [class, method].'
            )
        );
    });

    it('rejects a pair whose class does not exist', function () {
        expect(fn () => new MethodPairEntry('bad', ['Stubs\Missing', 'handle']))->toThrow(
            new InvalidArgumentException('Cannot register entry "bad": class "Stubs\Missing" does not exist.')
        );
    });

    it('rejects a method that does not exist', function () {
        expect(fn () => new MethodPairEntry('bad', [SomeClass::class, 'missing']))->toThrow(
            new InvalidArgumentException(
                'Cannot register entry "bad": method "Stubs\SomeClass::missing()" does not exist.'
            )
        );
    });

    it('rejects a non-public method', function () {
        expect(fn () => new MethodPairEntry('bad', [MultiParamStub::class, 'hidden']))->toThrow(
            new InvalidArgumentException(
                'Cannot register entry "bad": method "Stubs\MultiParamStub::hidden()" is not public.'
            )
        );
    });

    it('rejects a non-string method slot with a type-safe message', function () {
        expect(fn () => new MethodPairEntry('bad', [SomeClass::class, new stdClass]))->toThrow(
            new InvalidArgumentException(
                'Cannot register entry "bad": method "Stubs\SomeClass::stdClass()" does not exist.'
            )
        );
    });

    it('rejects a non-string class slot with a type-safe message', function () {
        expect(fn () => new MethodPairEntry('bad', [[], 'handle']))->toThrow(
            new InvalidArgumentException('Cannot register entry "bad": class "array" does not exist.')
        );
    });

    it('produces by handing the pair and args to the handler', function () {
        $foo = new ConcreteBar(null);
        $container = new StubContainer([AbstractFoo::class => $foo]);
        $handler = new Handler($container);
        $entry = new MethodPairEntry('handle', 'Stubs\SomeClass::handle');

        expect($entry->build($handler, $container, ['text' => 'hi']))->toBe('hi');
    });

    it('produces from a static Class::method pair without touching the container', function () {
        $container = new StubContainer([]);
        $handler = new Handler($container);
        $entry = new MethodPairEntry('static', 'Stubs\Dummy::staticMethod');

        expect($entry->build($handler, $container, ['value']))->toBe('value');
    });

    it('produces from an object pair as-is', function () {
        $factory = new SomeClass;
        $container = new StubContainer([]);
        $handler = new Handler($container);
        $entry = new MethodPairEntry('object-pair', [$factory, 'shouldCalled']);

        expect($entry->build($handler, $container, ['given']))->toBe('given');
    });

    it('derives the extension target from a named class return type', function () {
        $entry = new MethodPairEntry('chain', [MultiParamStub::class, 'chain']);

        expect($entry->extensionTarget(new EntryCollector))->toBe('Stubs\MultiParamStub');
    });

    it('derives no extension target from a builtin return type', function () {
        $entry = new MethodPairEntry('handle', 'Stubs\SomeClass::handle');

        expect($entry->extensionTarget(new EntryCollector))->toBeNull();
    });
});
