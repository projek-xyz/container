<?php

declare(strict_types=1);

use Projek\Container\Entry;
use Projek\Container\Entry\CallableEntry;
use Projek\Container\EntryCollector;
use Projek\Container\InvalidArgumentException;
use Projek\Container\NotFoundException;

use function Kahlan\describe;
use function Kahlan\expect;
use function Kahlan\given;
use function Kahlan\it;

describe(EntryCollector::class, function () {
    given('collector', function () {
        return new EntryCollector;
    });

    it('should store and return an Entry by id', function () {
        $entry = new CallableEntry('foo', fn () => 'bar');

        $this->collector['foo'] = $entry;

        expect($this->collector['foo'])->toBe($entry);
        expect($this->collector['foo'])->toBeAnInstanceOf(Entry::class);
        expect(isset($this->collector['foo']))->toBeTruthy();
        expect(isset($this->collector['baz']))->toBeFalsy();
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
            fn () => $this->collector['not-exists']
        )->toThrow(
            new NotFoundException('not-exists')
        );
    });

    it('should not allow entry removal', function () {
        $this->collector['foo'] = new CallableEntry('foo', fn () => 'bar');

        expect(function () {
            unset($this->collector['foo']);
        })->toThrow(
            new InvalidArgumentException('Removing registered entry "foo" is not supported.')
        );
    });
});
