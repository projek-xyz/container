<?php

declare(strict_types=1);

namespace Projek\Container;

use ArrayAccess;
use IteratorAggregate;

/**
 * Internal storage for container entries.
 *
 * Holds the registered `Entry` objects and enforces that entries cannot be
 * removed. The `offsetSet()` parameters stay `mixed` — narrowing them to
 * `Entry` violates the `ArrayAccess` parameter contravariance (fatal).
 *
 * @internal
 *
 * @template-implements ArrayAccess<string, Entry>
 * @template-implements IteratorAggregate<string, Entry>
 */
final class EntryCollector implements ArrayAccess, IteratorAggregate
{
    /**
     * @var array<string, Entry> List of registered entries.
     */
    private array $entries = [];

    /**
     * Create new instance.
     *
     * @param  iterable<string, Entry>  $entries
     */
    public function __construct(iterable $entries = [])
    {
        foreach ($entries as $id => $entry) {
            $this->offsetSet($id, $entry);
        }
    }

    /**
     * {@inheritdoc}
     *
     * @return \Traversable<string, Entry>
     */
    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->entries);
    }

    /**
     * {@inheritdoc}
     *
     * @param  string  $id
     */
    public function offsetExists(mixed $id): bool
    {
        return isset($this->entries[$id]);
    }

    /**
     * The only construction site of a NotFoundException: an id missing here is
     * genuinely absent, so the label it carries is always truthful (§7/§13).
     *
     * {@inheritdoc}
     *
     * @param  string  $id
     * @return Entry
     */
    public function offsetGet(mixed $id): mixed
    {
        if (! isset($this->entries[$id])) {
            throw new NotFoundException($id);
        }

        return $this->entries[$id];
    }

    /**
     * {@inheritdoc}
     *
     * @param  string  $id
     * @param  mixed  $entry  The `Entry` instance registered by `Container::set()`.
     */
    public function offsetSet(mixed $id, mixed $entry): void
    {
        $this->entries[$id] = $entry;
    }

    /**
     * {@inheritdoc}
     *
     * @param  string  $id
     *
     * @throws InvalidArgumentException Always, as removing registered entries is not supported.
     */
    public function offsetUnset(mixed $id): void
    {
        throw new InvalidArgumentException(
            \sprintf('Removing registered entry "%s" is not supported.', (string) $id)
        );
    }
}
