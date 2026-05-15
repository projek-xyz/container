<?php

declare(strict_types=1);

namespace Projek\Container\Events;

/**
 * @package Projek\Container
 */
final class BeforeRegistration
{
    /**
     * @param array{class-string<object>|string,string}|callable|object|string $factory
     * @param string $id
     */
    public function __construct(
        public array|object|string $factory,
        public string $id,
    ) {
        // .
    }
}
