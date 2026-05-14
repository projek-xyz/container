<?php

declare(strict_types=1);

namespace Projek\Container;

use ArrayAccess;
use Psr\Container\ContainerInterface;

/**
 * @package Projek\Container
 * @internal
 */
final class EntryCollector implements ArrayAccess
{
    /**
     * @var array<string, object|callable>
     */
    private array $entries = [];

    public function __construct(array $entries = [])
    {
        foreach ($entries as $offset => $value) {
            $this->offsetSet($offset, $value);
        }
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->entries[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->entries[$offset];
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        if (\is_object($value) && $value instanceof ContainerAware && null === $value->getContainer()) {
            $value->setContainer($this[ContainerInterface::class]);
        }

        $this->entries[$offset] = $value;
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->entries[$offset]);
    }
}
