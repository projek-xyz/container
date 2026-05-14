<?php

declare(strict_types=1);

namespace Projek\Container;

use ArrayAccess;
use IteratorAggregate;
use Psr\Container\ContainerInterface;

/**
 * Internal storage for container entries.
 *
 * This class handles the storage of resolved instances and factories,
 * ensuring that ContainerAware instances are properly initialized.
 *
 * @package Projek\Container
 * @internal
 * @template-implements ArrayAccess<string, object|callable>
 * @template-implements IteratorAggregate<string, object|callable>
 */
final class EntryCollector implements ArrayAccess, IteratorAggregate
{
    /**
     * @var array<string, object|callable> List of registered entries.
     */
    private array $entries = [];

    /**
     * Create new instance.
     *
     * @param iterable<string, object|callable> $entries
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
     * @return \Traversable<string, object|callable>
     */
    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->entries);
    }

    /**
     * {@inheritdoc}
     *
     * @param string $id
     */
    public function offsetExists(mixed $id): bool
    {
        return isset($this->entries[$id]);
    }

    /**
     * {@inheritdoc}
     *
     * @param string $id
     * @return object|callable
     */
    public function offsetGet(mixed $id): mixed
    {
        $entry = $this->entries[$id];

        if ($entry instanceof ContainerAware && null === $entry->getContainer()) {
            $entry->setContainer($this[ContainerInterface::class]);
        }

        return $entry;
    }

    /**
     * {@inheritdoc}
     *
     * @param string $id
     * @param object|callable $entry
     */
    public function offsetSet(mixed $id, mixed $entry): void
    {
        $this->entries[$id] = $entry;
    }

    /**
     * {@inheritdoc}
     *
     * @param string $id
     * @throws Exception Always, as removing registered entries is not supported.
     */
    public function offsetUnset(mixed $id): void
    {
        throw new Exception(
            \sprintf('Removing registered entry "%s" is not supported.', $id)
        );
    }
}
