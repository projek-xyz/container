<?php

declare(strict_types=1);

namespace Projek\Container;

use Psr\Container\ContainerExceptionInterface;

class RangeException extends \RangeException implements ContainerExceptionInterface
{
    /**
     * {@inheritDoc}
     */
    public function __construct(int $expected, int $actual, \Throwable $prev = null)
    {
        parent::__construct(sprintf('Could not accept more than %d arguments, %d given', $expected, $actual), 0, $prev);
    }
}
