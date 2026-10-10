<?php

declare(strict_types=1);

namespace Projek\Container;

use Psr\Container\NotFoundExceptionInterface;

/**
 * Exception thrown when a requested entry is not found in the container.
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
     * Retrieve the name of the missing entry.
     */
    public function getName(): string
    {
        return $this->name;
    }
}
