<?php

declare(strict_types=1);

namespace Projek\Container;

use Psr\Container\ContainerExceptionInterface;

/**
 * @package Projek\Container
 */
class InvalidArgumentException extends \InvalidArgumentException implements ContainerExceptionInterface
{
    // .
}
