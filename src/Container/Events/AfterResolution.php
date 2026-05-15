<?php

declare(strict_types=1);

namespace Projek\Container\Events;

/**
 * @package Projek\Container
 */
final class AfterResolution
{
    public array|object|string $entry;

    public function __construct(
        object|callable $entry,
        public string $id,
    ) {
        $this->entry = $entry;
    }
}
