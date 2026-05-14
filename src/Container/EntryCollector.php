<?php

declare(strict_types=1);

namespace Projek\Container;

use ArrayAccess;
use IteratorAggregate;
use Psr\Container\ContainerInterface;

/**
 * @package Projek\Container
 * @internal
 */
final class EntryCollector implements ArrayAccess, IteratorAggregate
{
    /**
     * @var array<string, object|callable>
     */
    private array $entries = [];

    public function __construct(iterable $entries = [])
    {
        foreach ($entries as $id => $entry) {
            $this->offsetSet($id, $entry);
        }
    }

    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->entries);
    }

    public function offsetExists(mixed $id): bool
    {
        return isset($this->entries[$id]);
    }

    public function offsetGet(mixed $id): mixed
    {
        return $this->entries[$id];
    }

    public function offsetSet(mixed $id, mixed $entry): void
    {
        if ($entry instanceof ContainerAware && null === $entry->getContainer()) {
            $entry->setContainer($this[ContainerInterface::class]);
        }

        $this->entries[$id] = $entry;
    }

    public function offsetUnset(mixed $id): void
    {
        unset($this->entries[$id]);
    }
}
