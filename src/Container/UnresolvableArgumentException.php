<?php

declare(strict_types=1);

namespace Projek\Container;

use Psr\Container\ContainerExceptionInterface;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use ReflectionParameter;

/**
 * @package Projek\Container
 */
final class UnresolvableArgumentException extends \RuntimeException implements ContainerExceptionInterface
{
    private ?string $caller = null;

    private ?string $className = null;

    private string $methodName;

    private int $position;

    /**
     * @param string $entry
     * @param ReflectionParameter $param
     * @param ReflectionFunctionAbstract $ref
     * @param \Throwable|null $prev
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

    public function getClassName(): ?string
    {
        return $this->className;
    }

    public function getMethodName(): string
    {
        return $this->methodName;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    final public function getCaller(): string
    {
        return $this->caller ??= implode('::', array_filter([
            $this->getClassName(),
            $this->getMethodName(),
        ]));
    }
}
