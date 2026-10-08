<?php

declare(strict_types=1);

namespace Projek\Container\Events;

/**
 * Dispatched after a fresh get() build has been cached — only when the entry
 * is not infrastructure (auto), not an alias, and the value is an object.
 */
final class EntryResolved
{
    public function __construct(
        public readonly string $id,
        public readonly object $instance,
    ) {}
}
