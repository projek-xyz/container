<?php

declare(strict_types=1);

namespace Projek\Container\Events;

/**
 * @package Projek\Container
 * @codeCoverageIgnore
 */
final class BeforeResolution
{
    public function __construct(
        public string $id,
    ) {
        // .
    }
}
