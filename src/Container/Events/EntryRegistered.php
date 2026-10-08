<?php

declare(strict_types=1);

namespace Projek\Container\Events;

use Projek\Container\Entry;

/**
 * Dispatched at the end of every user registration (set(), including an
 * auto-entry replacement, and setEventDispatcher()); payloads are readonly —
 * listeners observe, they no longer mutate.
 */
final class EntryRegistered
{
    public function __construct(public readonly Entry $entry) {}
}
