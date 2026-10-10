<?php

declare(strict_types=1);

namespace Projek\Container;

use Psr\Container\NotFoundExceptionInterface;

/**
 * Exception thrown when `get()` or `extend()` asks for an id the container never registered —
 * including a dangling alias target. Implements PSR-11's `NotFoundExceptionInterface`, so it is
 * catchable as `Psr\Container\NotFoundExceptionInterface`; `getName()` returns the missing id.
 */
final class NotFoundException extends \RuntimeException implements NotFoundExceptionInterface
{
    /**
     * Create a new NotFoundException instance.
     *
     * @param  string  $name  The name of the missing entry.
     * @param  \Throwable|null  $prev  The previous exception if any.
     */
    public function __construct(
        private string $name,
        ?\Throwable $prev = null,
    ) {
        parent::__construct(\sprintf('Container entry "%s" not found.', $name), 0, $prev);
    }

    /**
     * The id of the missing entry — the same value carried in the exception message.
     */
    public function getName(): string
    {
        return $this->name;
    }
}
