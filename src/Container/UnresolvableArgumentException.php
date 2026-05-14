<?php

declare(strict_types=1);

namespace Projek\Container;

use Psr\Container\ContainerExceptionInterface;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use ReflectionParameter;

/**
 * Exception thrown when a dependency for a function or method argument cannot be resolved.
 *
 * @package Projek\Container
 */
final class UnresolvableArgumentException extends \RuntimeException implements ContainerExceptionInterface
{
    /**
     * @var string|null The fully qualified name of the caller (Class::method).
     */
    private ?string $caller = null;

    /**
     * @var string|null The name of the class where the unresolvable argument is located.
     */
    private ?string $className = null;

    /**
     * @var string The name of the method where the unresolvable argument is located.
     */
    private string $methodName;

    /**
     * @var int The 1-based position of the unresolvable argument.
     */
    private int $position;

    /**
     * Create a new UnresolvableArgumentException instance.
     *
     * @param string $entry The name of the entry that could not be resolved.
     * @param ReflectionParameter $param The reflection of the unresolvable parameter.
     * @param ReflectionFunctionAbstract $ref The reflection of the function/method.
     * @param \Throwable|null $prev The previous exception if any.
     */
    public function __construct(
        string $entry,
        ReflectionParameter $param,
        ReflectionFunctionAbstract $ref,
        ?\Throwable $prev = null,
    ) {
        if ($ref instanceof ReflectionMethod) {
            $this->className = $ref->getDeclaringClass()->getName();
        }

        $this->methodName = $ref->getName();

        $this->position = $param->getPosition() + 1;

        $message = \sprintf(
            '%s(): Argument #%d ($%s) depends on entry "%s" of non-exists',
            $this->getCaller(),
            $this->getPosition(),
            $param->getName(),
            $entry,
        );

        parent::__construct($message, 0, $prev);
    }

    /**
     * Get the class name where the error occurred.
     *
     * @return string|null
     */
    public function getClassName(): ?string
    {
        return $this->className;
    }

    /**
     * Get the method name where the error occurred.
     *
     * @return string
     */
    public function getMethodName(): string
    {
        return $this->methodName;
    }

    /**
     * Get the argument position.
     *
     * @return int
     */
    public function getPosition(): int
    {
        return $this->position;
    }

    /**
     * Get the full caller identifier (Class::method or function).
     *
     * @return string
     */
    final public function getCaller(): string
    {
        return $this->caller ??= implode('::', array_filter([
            $this->getClassName(),
            $this->getMethodName(),
        ]));
    }
}
