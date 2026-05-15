<?php

declare(strict_types=1);

namespace Projek\Container\Events;

/**
 * @package Projek\Container
 */
final class BeforeResolution
{
    public function __construct(
        public string $id,
    ) {
        // .
    }
}
