<?php

declare(strict_types=1);

namespace Projek\Container;

use Psr\Container\ContainerExceptionInterface;

/**
 * @package Projek\Container
 */
class Exception extends \RuntimeException implements ContainerExceptionInterface
{
    /**
     * @param string $message
     * @param \Throwable|null $prev
     */
    public function __construct(string $message, ?\Throwable $prev = null)
    {
        parent::__construct($message, ($prev ? $prev->getCode() : 0), $prev);
    }
}
