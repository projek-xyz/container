<?php

declare(strict_types=1);

use Projek\Container;
use Projek\Container\ContainerAware;
use Projek\Container\EntryCollector;
use Projek\Container\Exception;
use Projek\Container\HasContainer;
use Projek\Container\NotFoundException;
use Psr\Container\ContainerInterface;

describe(EntryCollector::class, function () {
    given('collector', function () {
        return new EntryCollector();
    });

    it('should be able to set and get an entry', function () {
        $this->collector['foo'] = 'bar';

        expect($this->collector['foo'])->toBe('bar');
        expect(isset($this->collector['foo']))->toBeTruthy();
    });

    it('should be able to iterate over entries', function () {
        $entries = [
            'foo' => 'bar',
            'baz' => 'qux',
        ];

        $collector = new EntryCollector($entries);
        $result = [];

        foreach ($collector as $id => $entry) {
            $result[$id] = $entry;
        }

        expect($result)->toBe($entries);
    });

    it('should throw NotFoundException for missing entries', function () {
        expect(
            fn () => $this->collector['not-exists']
        )->toThrow(
            new NotFoundException('not-exists')
        );
    });

    it('should inject container to ContainerAware entries', function () {
        $container = new Container();
        $this->collector[ContainerInterface::class] = $container;

        $stub = new class implements ContainerAware {
            use HasContainer;
        };

        $this->collector['stub'] = $stub;

        expect($stub->getContainer())->toBeNull();

        $entry = $this->collector['stub'];

        expect($entry)->toBe($stub);
        expect($entry->getContainer())->toBe($container);
    });

    it('should not recurse infinitely if ContainerInterface itself is ContainerAware', function () {
        $stub = new class implements ContainerAware {
            use HasContainer;
        };

        $this->collector[ContainerInterface::class] = $stub;

        // This should not trigger infinite recursion
        $entry = $this->collector[ContainerInterface::class];

        expect($entry)->toBe($stub);
        expect($entry->getContainer())->toBeNull();
    });

    it('should not allow entry removal', function () {
        $this->collector['foo'] = 'bar';

        expect(function () {
            unset($this->collector['foo']);
        })->toThrow(
            new Exception('Removing registered entry "foo" is not supported.')
        );
    });
});
