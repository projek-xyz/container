<?php

declare(strict_types=1);

namespace Projek\Container;

use Projek\Container;
use Psr\Container\ContainerInterface;

/**
 * Container factory resolver class.
 *
 * An internal class mainly used for resolving and handling container factories.
 *
 * @package Projek\Container
 * @internal
 *
 * @template TCallable of \CLosure|string|array{class-string|string, string}
 * @template TArgs of array<int, mixed>
 */
final class Resolver
{
    /**
     * @var ContainerInterface Container instance.
     */
    private $container;

    /**
     * Create instance.
     *
     * @param Container $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * Entry resolver.
     *
     * Ensure the given argument is a callable.
     *
     * @param TCallable $entry
     * @param TArgs $args
     * @return object|callable
     * @throws \Projek\Container\Exception
     * @throws \Projek\Container\InvalidArgumentException
     */
    public function resolve($entry, array $args = [])
    {
        if (\is_string($entry) && ! \function_exists($entry)) {
            $entry = \str_contains($entry, '::')
                ? \explode('::', $entry)
                : $this->createInstance($entry, $args);
        }

        if (\is_array($entry) && \is_string($entry[0])) {
            $entry[0] = $this->resolve($entry[0], $args);
        }

        if (\is_object($entry) || \is_callable($entry)) {
            return $entry;
        }

        throw new InvalidArgumentException(
            \sprintf('Cannot resolve invalid entry of "%s"', \gettype($entry))
        );
    }

    /**
     * Handle callable.
     *
     * @param TCallable $callable
     * @param TArgs $args
     * @return mixed
     * @throws \Projek\Container\Exception
     * @throws \Projek\Container\InvalidArgumentException
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    public function handle($callable, array $args = []): mixed
    {
        if (\is_object($callable)) {
            // Returns the object if it was non-callable instance.
            if (! \is_callable($callable)) {
                return $callable;
            }

            // Otherwise convert it to closure.
            $callable = \Closure::fromCallable($callable);
        }

        $ref = $this->createCallableReflection($callable);
        $caller = $ref->getName();

        /** @var array{object|null, TArgs} */
        $params = [];

        if ($ref instanceof \ReflectionMethod) {
            $caller = $ref->getDeclaringClass()->getName() . '::' . $ref->getName();
            $params[] = $ref->isStatic() && ! \is_object($callable[0]) ? null : $callable[0];
        }

        try {
            $params[] = $this->resolveArgs($ref, $args);

            return $ref->invokeArgs(...$params);
        } catch (Exception $err) {
            throw new Exception($caller . '(): ' . $err->getMessage(), $err->getPrevious());
        }
    }

    /**
     * Create an instance of $className.
     *
     * @param string $className
     * @param TArgs $args
     * @return object|mixed
     * @throws \Projek\Container\Exception
     *  When $className is not instantiable or its constructor depends on non-exists container entry.
     */
    private function createInstance(string $className, array $args = [])
    {
        if ($this->container->has($className)) {
            return $this->container->get($className);
        }

        if (! \class_exists($className)) {
            throw new Exception(
                \sprintf('Cannot resolve an entry or class named "%s" of non-exists', $className)
            );
        }

        $ref = new \ReflectionClass($className);

        if (! $ref->isInstantiable()) {
            throw new Exception(
                \sprintf('Cannot instantiate class named "%s"', $className)
            );
        }

        try {
            $args = ($constructor = $ref->getConstructor())
                ? $this->resolveArgs($constructor, $ref->hasMethod('__invoke') ? [] : $args)
                : [];

            return $ref->newInstanceArgs($args);
        } catch (Exception $err) {
            throw new Exception($className . '::__construct(): ' . $err->getMessage(), $err->getPrevious());
        }
    }

    /**
     * Instance resolver.
     *
     * @param TCallable $callable
     * @return \ReflectionMethod|\ReflectionFunction
     * @throws \Projek\Container\Exception
     * @throws \Projek\Container\InvalidArgumentException
     */
    private function createCallableReflection($callable)
    {
        if (\is_string($callable) && \str_contains($callable, '::')) {
            $callable = \explode('::', $callable);
        }

        if (! \is_array($callable)) {
            return new \ReflectionFunction($callable);
        }

        try {
            $ref = new \ReflectionMethod($callable[0], $callable[1]);
        } catch (\ReflectionException $err) {
            throw new InvalidArgumentException($err->getMessage(), $err->getCode(), $err);
        }

        // If trying to statically call a non-static method (at least on PHP 7.x)
        if (! $ref->isStatic() && \is_string($callable[0])) {
            throw new Exception(
                \sprintf('Non-static method %s should not be called statically', \join('::', $callable))
            );
        }

        return $ref;
    }

    /**
     * Callable arguments resolver.
     *
     * @param \ReflectionFunctionAbstract $reflection
     * @param array<int, mixed> $args
     * @return TArgs
     * @throws \Projek\Container\Exception
     */
    private function resolveArgs(\ReflectionFunctionAbstract $reflection, array $args = []): array
    {
        foreach ($reflection->getParameters() as $param) {
            // Just skip if parameter already provided.
            if (\array_key_exists($position = $param->getPosition(), $args)) {
                continue;
            }

            $type = $param->getType();
            $typeName = ($type instanceof \ReflectionNamedType && ! $type->isBuiltin())
                ? $type->getName()
                : $param->getName();

            try {
                $args[$position] = $this->container->get($typeName);
            } catch (NotFoundException $err) {
                if (! $param->isOptional()) {
                    throw new Exception(\sprintf(
                        'Argument #%d ($%s) depends on entry "%s" of non-exists',
                        ++$position,
                        $param->getName(),
                        $err->getName()
                    ), $err);
                }

                $args[$position] = $param->getDefaultValue();
            }
        }

        return $args;
    }
}
