<?php

declare(strict_types=1);

namespace Projek\Container;

use Psr\Container\ContainerExceptionInterface;

/**
 * Base exception for the Container package.
 *
 * This exception is thrown when a general error occurs within the container,
 * such as a failed resolution or an invalid configuration.
 *
 * @package Projek\Container
 */
class Exception extends \RuntimeException implements ContainerExceptionInterface
{
    /**
     * Create a new Exception instance.
     *
     * @param string $message The error message.
     * @param \Throwable|null $prev The previous exception if any.
     */
    public function __construct(string $message, ?\Throwable $prev = null)
    {
        parent::__construct($message, ($prev ? $prev->getCode() : 0), $prev);
    }
}
