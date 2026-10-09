<?php

declare(strict_types=1);

use Projek\Container\Entry;
use Projek\Container\Entry\CallableEntry;
use Projek\Container\EntryCollector;
use Projek\Container\InvalidArgumentException;
use Projek\Container\NotFoundException;

use function Kahlan\describe;
use function Kahlan\expect;
use function Kahlan\it;

describe(EntryCollector::class, function () {
    it('should store and return an Entry by id', function () {
        $collector = new EntryCollector;
        $entry = new CallableEntry('foo', fn () => 'bar');

        $collector['foo'] = $entry;

        expect($collector['foo'])->toBe($entry);
        expect($collector['foo'])->toBeAnInstanceOf(Entry::class);
        expect(isset($collector['foo']))->toBeTruthy();
        expect(isset($collector['baz']))->toBeFalsy();
    });

    it('should accept initial Entry storage through the constructor', function () {
        $entry = new CallableEntry('foo', fn () => 'bar');
        $collector = new EntryCollector(['foo' => $entry]);

        expect($collector['foo'])->toBe($entry);
    });

    it('should be able to iterate over entries', function () {
        $foo = new CallableEntry('foo', fn () => 'bar');
        $baz = new CallableEntry('baz', fn () => 'qux');

        $collector = new EntryCollector([
            'foo' => $foo,
            'baz' => $baz,
        ]);

        $result = [];

        foreach ($collector as $id => $entry) {
            $result[$id] = $entry;
        }

        expect($result)->toBe(['foo' => $foo, 'baz' => $baz]);
    });

    it('should throw NotFoundException for missing entries', function () {
        expect(
            fn () => (new EntryCollector)['not-exists']
        )->toThrow(new NotFoundException('not-exists'));
    });

    it('should not allow entry removal', function () {
        $collector = new EntryCollector;
        $collector['foo'] = new CallableEntry('foo', fn () => 'bar');

        expect(function () use ($collector) {
            unset($collector['foo']);
        })->toThrow(InvalidArgumentException::removalNotSupported('foo'));
    });
});
