<?php

declare(strict_types=1);

namespace Projek\Container\Events;

/**
 * Dispatched after a fresh get() build has been cached — only when the entry
 * is not infrastructure (auto), not an alias, and the value is an object.
 */
final class EntryResolved
{
    /**
     * @param  string  $id  The resolved entry's identifier — the alias target for aliases.
     * @param  object  $instance  The freshly built, already-cached value.
     */
    public function __construct(
        public readonly string $id,
        public readonly object $instance,
    ) {}
}
