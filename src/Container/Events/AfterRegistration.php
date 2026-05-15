<?php

declare(strict_types=1);

namespace Projek\Container\Events;

/**
 * @package Projek\Container
 */
final class AfterRegistration
{
    public function __construct(
        /** @var callable|object $entry */
        public array|object|string $entry,
        public string $id,
    ) {
        // .
    }
}
