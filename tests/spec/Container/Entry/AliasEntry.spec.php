<?php

declare(strict_types=1);

use Projek\Callable\Handler;
use Projek\Container\Entry\AliasEntry;
use Projek\Container\Entry\CallableEntry;
use Projek\Container\Entry\ClassNameEntry;
use Projek\Container\EntryCollector;
use Projek\Container\NotFoundException;
use Stubs\SomeClass;
use Stubs\StubContainer;

use function Kahlan\describe;
use function Kahlan\expect;
use function Kahlan\it;

describe(AliasEntry::class, function () {
    it('accepts any target string without validating it (set() owns that check)', function () {
        $entry = new AliasEntry('alias', 'not-registered-yet', true);

        expect($entry->id)->toBe('alias');
        expect($entry->factory)->toBe('not-registered-yet');
        expect($entry->parameters)->toBe([]);
        expect($entry->returnType)->toBeNull();
        expect($entry->auto)->toBeTruthy();
        expect($entry->getFactory())->toBe('not-registered-yet');
    });

    it('produces by resolving the target through the container', function () {
        $container = new StubContainer(['target' => 'the-value']);
        $handler = new Handler(new StubContainer([]));
        $entry = new AliasEntry('alias', 'target');

        expect($entry->build($handler, $container))->toBe('the-value');
    });

    it('propagates a missing target as a NotFoundException', function () {
        $container = new StubContainer([]);
        $handler = new Handler(new StubContainer([]));
        $entry = new AliasEntry('alias', 'target');

        expect(fn () => $entry->build($handler, $container))->toThrow(new NotFoundException('target'));
    });

    it('derives no extension target when the chain dead-ends', function () {
        $entry = new AliasEntry('alias', 'nowhere');
        $collector = new EntryCollector;

        expect($entry->extensionTarget($collector))->toBeNull();
    });

    it('follows the alias chain to the target entry', function () {
        $collector = new EntryCollector([
            'class' => new ClassNameEntry('class', SomeClass::class),
        ]);
        $inner = new AliasEntry('inner', 'class');
        $outer = new AliasEntry('outer', 'inner');

        $collector['inner'] = $inner;

        expect($inner->extensionTarget($collector))->toBe('Stubs\SomeClass');
        expect($outer->extensionTarget($collector))->toBe('Stubs\SomeClass');
    });

    it('derives no extension target when the chained target is not derivable', function () {
        $collector = new EntryCollector([
            'callable' => new CallableEntry('callable', fn ($value) => $value),
        ]);
        $entry = new AliasEntry('alias', 'callable');

        expect($entry->extensionTarget($collector))->toBeNull();
    });
});
