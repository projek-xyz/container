<?php

declare(strict_types=1);

namespace Projek\Container;

use Closure;
use Projek\Container;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use ReflectionException;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use ReflectionNamedType;

/**
 * Container factory resolver class.
 *
 * An internal class mainly used for resolving and handling container factories.
 *
 * @package Projek\Container
 * @internal
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
     * Entry factory resolver.
     *
     * Ensure the given argument is a callable.
     *
     * @param array{class-string<object>,string}|callable|object|string $factory
     * @param array<int, mixed> $args
     * @return callable|object
     * @throws Exception
     * @throws InvalidArgumentException
     */
    public function resolve(
        array|callable|object|string $factory,
        array $args = []
    ): callable|object {
        if (\is_string($factory) && ! \function_exists($factory)) {
            $factory = \str_contains($factory, '::')
                ? \explode('::', $factory)
                : $this->createInstance($factory, $args);
        }

        if (\is_array($factory) && \is_string($factory[0] ?? null)) {
            $factory[0] = $this->resolve($factory[0], $args);
        }

        if (\is_object($factory) || \is_callable($factory)) {
            return $factory;
        }

        throw new InvalidArgumentException(
            \sprintf('Cannot resolve invalid entry of "%s"', \gettype($factory))
        );
    }

    /**
     * Handle callable.
     *
     * @template TArgs of array<int, mixed>
     *
     * @param array{object|string,string}|callable|object|string $callable
     * @param TArgs $args
     * @return ($callable is object ? object : mixed)
     * @throws Exception
     * @throws InvalidArgumentException
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
            $callable = Closure::fromCallable($callable);
        }

        try {
            $ref = $this->createCallableReflection($callable);

            if ($ref instanceof ReflectionFunction) {
                return $ref->invokeArgs(
                    $this->resolveArgs($ref, $args)
                );
            }

            /** @var array{object|string,string} $callable */
            return $ref->invokeArgs(
                \is_object($callable[0]) ? $callable[0] : null,
                $this->resolveArgs($ref, $args)
            );
        } catch (ReflectionException $err) {
            throw new InvalidArgumentException($err->getMessage(), $err->getCode(), $err);
        } catch (UnresolvableArgumentException $err) {
            throw new Exception($err->getMessage(), $err->getPrevious());
        }
    }

    /**
     * Create an instance of $className.
     *
     * @template TObj of object
     * @template TArgs of array<int, mixed>
     *
     * @param class-string<TObj>|string $className
     * @param TArgs $args
     * @return ($className is class-string<TObj> ? TObj : mixed)
     * @throws Exception
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

        $ref = new ReflectionClass($className);

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
        } catch (UnresolvableArgumentException $err) {
            throw new Exception($err->getMessage(), $err->getPrevious());
        }
    }

    /**
     * Instance resolver.
     *
     * @param array{object|string,string}|callable|object|string $callable
     * @return ReflectionMethod|ReflectionFunction
     * @throws Exception
     * @throws ReflectionException
     */
    private function createCallableReflection(
        array|callable|object|string $callable
    ): ReflectionMethod|ReflectionFunction {
        // Split the $callable of `ClassName::method` into `[ClassName, method]`.
        if (\is_string($callable) && \str_contains($callable, '::')) {
            $callable = \explode('::', $callable);
        }

        // Pass non-array $callable directly to `ReflectionFunction` that possibly
        // a callable object including a `Closure`, or a string of function name
        if (! \is_array($callable)) {
            /** @var Closure|string $callable */
            return new ReflectionFunction($callable);
        }

        /** @var array{object|string,string} $callable */
        $ref = new ReflectionMethod($callable[0], $callable[1]);

        // If trying to statically call a non-static method (at least on PHP 7.x)
        if (! $ref->isStatic() && \is_string($callable[0])) {
            throw new Exception(\sprintf(
                'Non-static method %s should not be called statically',
                \join('::', $callable)
            ));
        }

        return $ref;
    }

    /**
     * Callable arguments resolver.
     *
     * @param ReflectionFunctionAbstract $ref
     * @param array<int, mixed> $args
     * @return array<int, mixed>
     * @throws Exception
     */
    private function resolveArgs(ReflectionFunctionAbstract $ref, array $args = []): array
    {
        foreach ($ref->getParameters() as $param) {
            // Just skip if parameter already provided.
            if (\array_key_exists($position = $param->getPosition(), $args)) {
                continue;
            }

            $type = $param->getType();
            $typeName = ($type instanceof ReflectionNamedType && ! $type->isBuiltin())
                ? $type->getName()
                : $param->getName();

            try {
                $args[$position] = $this->container->get($typeName);
            } catch (NotFoundException $err) {
                if (! $param->isOptional()) {
                    throw new UnresolvableArgumentException($err->getName(), $param, $ref, $err);
                }

                $args[$position] = $param->getDefaultValue();
            }
        }

        return $args;
    }
}
