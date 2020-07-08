<?php

declare(strict_types=1);

namespace Projek\Container;

use Psr\Container\ContainerExceptionInterface;

class InvalidArgumentException extends \InvalidArgumentException implements ContainerExceptionInterface
{
    /**
     * {@inheritDoc}
     */
    public function __construct(int $num, array $expected, string $actual, \Throwable $prev = null)
    {
        parent::__construct(sprintf(
            'Expect parameter %d to be %s, %s given',
            $num,
            implode(' or ', $expected),
            $actual
        ), 0, $prev);
    }
}