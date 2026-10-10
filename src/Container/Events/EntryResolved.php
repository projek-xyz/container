<?php

declare(strict_types=1);

namespace Projek\Container\Events;

/**
 * Dispatched after a fresh `get()` build has been cached — and only then: the entry must not be
 * an infrastructure (`auto`) default, must not be an alias, and the value must be an object. Cache
 * hits and `make()` never dispatch it; an alias dispatches exactly one event, carrying the target's
 * id.
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
