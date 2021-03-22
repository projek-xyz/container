<?php

declare(strict_types=1);

namespace Projek\Container;

use Psr\Container\ContainerExceptionInterface;

class Exception extends \RuntimeException implements ContainerExceptionInterface
{
    /**
     * Create instance.
     *
     * @param string $message
     * @param \Throwable|null $prev
     */
    public function __construct(string $message, ?\Throwable $prev = null)
    {
        parent::__construct($message, 0, $prev);
    }
}
