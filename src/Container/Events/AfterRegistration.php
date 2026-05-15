<?php

declare(strict_types=1);

namespace Projek\Container\Events;

/**
 * @package Projek\Container
 */
final class AfterRegistration
{
    public array|object|string $entry;

    public function __construct(
        array|callable|object|string $entry,
        public string $id,
    ) {
        $this->entry = $entry;
    }
}
