<?php

declare(strict_types=1);

namespace Projek\Container\Events;

use Projek\Container\Container;

/**
 * Event dispatched before a service entry is resolved.
 *
 * This event allows listeners to modify the entry ID before resolution
 * occurs, enabling service redirection or aliasing.
 *
 * @package Projek\Container
 * @see Container::get()
 */
final class BeforeResolution
{
    public function __construct(
        public string $id,
    ) {
        // .
    }
}
