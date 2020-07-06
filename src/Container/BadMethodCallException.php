<?php

declare(strict_types=1);

namespace Projek\Container;

use Psr\Container\ContainerExceptionInterface;

class BadMethodCallException extends \BadMethodCallException implements ContainerExceptionInterface
{
    /**
     * {@inheritDoc}
     */
    public function __construct(array $actual, \Throwable $prev = null)
    {
        $class = is_string($actual[0]) ? $actual[0] : get_class($actual[0]);

        parent::__construct(sprintf('Call to undefined method %s::%s()', $class, $actual[1]), 0, $prev);
    }
}
