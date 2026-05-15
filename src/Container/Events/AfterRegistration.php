<?php

declare(strict_types=1);

namespace Projek\Container\Events;

/**
 * Event dispatched after a service entry is registered.
 *
 * This event provides access to the resolved entry after it has been
 * stored in the container, allowing listeners to perform post-registration actions.
 *
 * @package Projek\Container
 * @see Container::set()
 */
final class AfterRegistration
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
