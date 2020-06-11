<?php

namespace Projek\Container;

use Psr\Container\NotFoundExceptionInterface;

class NotFoundException extends \InvalidArgumentException implements NotFoundExceptionInterface
{
    public function __construct($name, $code = 0, \Throwable $previous = null)
    {
        parent::__construct(sprintf('Container "%s" not found.', $name), $code, $previous);
    }
}
