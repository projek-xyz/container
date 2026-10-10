<?php

declare(strict_types=1);

namespace Projek\Container;

use Psr\Container\ContainerExceptionInterface;

/**
 * Exception thrown when a target — a registered entry, a `make()` class-string, or a callable
 * passed to `make()` — could not be built: dependency resolution failed (message pattern
 * `Failed to resolve "%s": %s`) or a circular reference was detected while building. Exceptions
 * thrown by user code propagate untouched and are never re-wrapped. Implements PSR-11's
 * `ContainerExceptionInterface`; the underlying package failure rides along in `getPrevious()` when
 * there is one.
 */
final class ResolutionException extends \RuntimeException implements ContainerExceptionInterface
{
    /**
     * @param  \Throwable|null  $previous  The previous exception (the error boundary passes the original cause through).
     */
    public function __construct(string $message, ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
