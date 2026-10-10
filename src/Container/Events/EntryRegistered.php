<?php

declare(strict_types=1);

namespace Projek\Container\Events;

use Projek\Container\Entry;

/**
 * Dispatched at the end of every user registration — `set()` (including an auto-entry replacement)
 * and `setEventDispatcher()`. The payload is readonly: listeners observe the registration, they
 * cannot redirect ids or replace entries. Infrastructure defaults inserted by the constructor,
 * `extend()` and `make()` never dispatch it.
 */
final class EntryRegistered
{
    /**
     * @param  Entry  $entry  The registered entry — a user registration or an infrastructure (auto) replacement.
     */
    public function __construct(public readonly Entry $entry) {}
}
