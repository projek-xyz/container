<?php

declare(strict_types=1);

namespace Projek\Container;

use Psr\Container\ContainerExceptionInterface;
use ReflectionFunction;
use ReflectionMethod;

/**
 * @package Projek\Container
 */
class UnresolvableArgumentException extends \RuntimeException implements ContainerExceptionInterface
{
    private string $caller;

    /**
     * @param int $position
     * @param string $name
     * @param string $entry
     * @param ReflectionFunction|ReflectionMethod $ref
     * @param \Throwable|null $prev
     */
    public function __construct(
        int $position,
        string $name,
        string $entry,
        ReflectionFunction|ReflectionMethod $ref,
        ?\Throwable $prev = null,
    ) {
        $this->caller = $ref instanceof ReflectionMethod
            ? $ref->getDeclaringClass()->getName() . '::' . $ref->getName()
            : $ref->getName();

        $message = \sprintf(
            'Argument #%d ($%s) depends on entry "%s" of non-exists',
            $position,
            $name,
            $entry,
        );

        parent::__construct($message, 0, $prev);
    }

    final public function getCaller(): string
    {
        return $this->caller;
    }
}
