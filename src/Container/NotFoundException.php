<?php

declare(strict_types=1);

namespace Projek\Container;

use Psr\Container\NotFoundExceptionInterface;

class NotFoundException extends \InvalidArgumentException implements NotFoundExceptionInterface
{
    /**
     * {@inheritDoc}
     */
    public function __construct($name, \Throwable $prev = null)
    {
        parent::__construct(sprintf('Container "%s" not found.', $name), 0, $prev);
    }
}
