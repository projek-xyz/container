<?php

declare(strict_types=1);

namespace Projek\Container\Events;

/**
 * Event dispatched after a service entry is resolved.
 *
 * This event provides access to the resolved entry and allows
 * listeners to perform actions after resolution completes.
 *
 * @package Projek\Container
 * @see Container::get()
 */
final class AfterResolution
{
    /**
     * @var callable|object The resolved entry.
     */
    private $entry;

    public function __construct(
        callable|object $entry,
        public string $id,
    ) {
        $this->entry = $entry;
    }

    /**
     * Get the resolved entry.
     *
     * @return callable|object
     */
    public function getEntry(): callable|object
    {
        return $this->entry;
    }

    /**
     * Set a new entry.
     *
     * @param callable|object $entry The new entry.
     */
    public function setEntry(callable|object $entry): void
    {
        $this->entry = $entry;
    }
}
