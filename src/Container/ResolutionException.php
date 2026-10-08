<?php

declare(strict_types=1);

namespace Projek\Container;

use Psr\Container\ContainerExceptionInterface;

/**
 * Exception thrown when a target — a registered entry, a make() class-string,
 * or a callable passed to make() — could not be built/resolved (anything but
 * a missing id).
 */
class ResolutionException extends \RuntimeException implements ContainerExceptionInterface
{
    /**
     * @param  \Throwable|null  $previous  The previous exception (the error boundary passes the original cause through).
     */
    public function __construct(string $message, ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
