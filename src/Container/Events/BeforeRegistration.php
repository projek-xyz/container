<?php

declare(strict_types=1);

namespace Projek\Container\Events;

/**
 * @package Projek\Container
 */
final class BeforeRegistration
{
    public array|object|string $factory;

    public function __construct(
        array|callable|object|string $factory,
        public string $id,
    ) {
        $this->factory = $factory;
    }
}
