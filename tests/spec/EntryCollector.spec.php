<?php

declare(strict_types=1);

use Projek\Container\ContainerAware;
use Projek\Container\EntryCollector;
use Projek\Container\Exception;
use Projek\Container\HasContainer;
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

    it('should inject container to ContainerAware entries', function () {
        $container = new Projek\Container();
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

    it('should not allow entry removal', function () {
        $this->collector['foo'] = 'bar';

        expect(function () {
            unset($this->collector['foo']);
        })->toThrow(
            new Exception('Removing registered entry "foo" is not supported.')
        );
    });
});
