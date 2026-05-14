<?php

declare(strict_types=1);

namespace Projek\Container;

use Psr\Container\ContainerExceptionInterface;

/**
 * Exception thrown when an invalid argument is provided during service resolution.
 *
 * @package Projek\Container
 */
class InvalidArgumentException extends \InvalidArgumentException implements ContainerExceptionInterface
{
    // .
}
