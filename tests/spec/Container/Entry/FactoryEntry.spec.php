<?php

declare(strict_types=1);

use Projek\Callable\Handler;
use Projek\Container\Entry\FactoryEntry;
use Projek\Container\EntryCollector;
use Projek\Container\EntryFactory;
use Psr\Container\ContainerInterface;
use Stubs\StubContainer;

use function Kahlan\describe;
use function Kahlan\expect;
use function Kahlan\it;

describe(FactoryEntry::class, function () {
    it('recognises an EntryFactory and captures the create() signature', function () {
        $factory = new class implements EntryFactory
        {
            public function create(ContainerInterface $container): object
            {
                return new stdClass;
            }
        };

        $entry = new FactoryEntry('factory', $factory, true);

        expect($entry->id)->toBe('factory');
        expect($entry->auto)->toBeTruthy();
        expect($entry->factory)->toBe($factory);
        expect($entry->parameters)->toBe(['container' => 'Psr\Container\ContainerInterface']);
        expect($entry->returnType)->toBe('object');
        expect($entry->getFactory())->toBe($factory);
    });

    it('produces by calling create() with the build container, ignoring args', function () {
        $factory = new class implements EntryFactory
        {
            public ?ContainerInterface $received = null;

            public function create(ContainerInterface $container): object
            {
                $this->received = $container;

                return new stdClass;
            }
        };

        $container = new StubContainer([]);
        $handler = new Handler(new StubContainer([]));
        $entry = new FactoryEntry('factory', $factory);

        $result = $entry->build($handler, $container, ['ignored' => 'args']);

        expect($result)->toBeAnInstanceOf(stdClass::class);
        expect($factory->received)->toBe($container);
    });

    it('derives the unrestricted extension target object', function () {
        $factory = new class implements EntryFactory
        {
            public function create(ContainerInterface $container): object
            {
                return new stdClass;
            }
        };

        $entry = new FactoryEntry('factory', $factory);

        expect($entry->extensionTarget(new EntryCollector))->toBe('object');
    });
});
