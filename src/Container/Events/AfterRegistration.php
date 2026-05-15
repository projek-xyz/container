<?php

declare(strict_types=1);

namespace Projek\Container\Events;

/**
 * @package Projek\Container
 */
final class AfterRegistration
{
    /**
     * @param array{class-string<object>|string,string}|callable|object|string $entry
     * @param string $id
     */
    public function __construct(
        public array|object|string $entry,
        public string $id,
    ) {
        // .
    }
}
