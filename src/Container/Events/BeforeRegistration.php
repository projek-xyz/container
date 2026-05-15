<?php

declare(strict_types=1);

namespace Projek\Container\Events;

/**
 * Event dispatched before a service entry is registered.
 *
 * This event allows listeners to modify the factory before it is
 * resolved and stored in the container, enabling dynamic factory replacement.
 *
 * @package Projek\Container
 * @see Container::set()
 */
final class BeforeRegistration
{
    /**
     * @var array{class-string<object>|string,string}|callable|string The service factory.
     */
    private $factory;

    /**
     * @param array{class-string<object>|string,string}|callable|string $factory
     */
    public function __construct(
        array|callable|string $factory,
        public string $id,
    ) {
        $this->factory = $factory;
    }

    /**
     * Set a new factory for the entry.
     *
     * @param array{class-string<object>|string,string}|callable|string $factory The new factory.
     */
    public function setFactory(array|callable|string $factory): void
    {
        $this->factory = $factory;
    }

    /**
     * Get the current factory.
     *
     * @return array{class-string<object>|string,string}|callable|string
     */
    public function getFactory(): array|callable|string
    {
        return $this->factory;
    }
}
