<?php

declare(strict_types=1);

namespace Projek\Container;

use Closure;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use ReflectionException;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use ReflectionNamedType;

/**
 * Internal service for resolving dependencies and instantiating classes.
 *
 * This class uses reflection to autowire dependencies and execute
 * various types of factories (closures, callables, etc.).
 *
 * @package Projek\Container
 * @internal This class is for internal use by the Container.
 */
final class Resolver
{
    /**
     * @var ContainerInterface The container instance used for dependency lookups.
     */
    private $container;

    /**
     * Create a new Resolver instance.
     *
     * @param ContainerInterface $container The parent container.
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * Convert various factory formats into executable callables or objects.
     *
     * This method ensures that class names are instantiated and class-method
     * strings are converted into valid callables.
     *
     * @param array{class-string<object>|string,string}|callable|object|string $factory
     * @param array<int, mixed> $args Optional constructor arguments.
     * @return callable|object
     * @throws Exception If the factory cannot be resolved.
     * @throws InvalidArgumentException If the factory format is invalid.
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
     * Execute a callable or return a non-callable object.
     *
     * This method handles the actual execution of factory functions and
     * autowires any parameters that are not explicitly provided.
     *
     * @template TArgs of array<int, mixed>
     *
     * @param array{object|string,string}|callable|object|string $callable
     * @param TArgs $args Explicit arguments to pass to the callable.
     * @return ($callable is object ? object : mixed)
     * @throws Exception If an argument cannot be resolved.
     * @throws InvalidArgumentException If reflection fails.
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
     * Instantiate a new class instance.
     *
     * @template TObj of object
     * @template TArgs of array<int, mixed>
     *
     * @param class-string<TObj>|string $className The class name to instantiate.
     * @param TArgs $args Optional constructor arguments.
     * @return ($className is class-string<TObj> ? TObj : mixed)
     * @throws Exception If the class is not found or not instantiable.
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
     * Create a reflection instance for the given callable.
     *
     * @param array{object|string,string}|callable|object|string $callable
     * @return ReflectionMethod|ReflectionFunction
     * @throws Exception If a non-static method is called statically.
     * @throws ReflectionException If reflection fails.
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
     * Resolve arguments for a function or method using autowiring.
     *
     * @param ReflectionFunctionAbstract $ref The reflection instance.
     * @param array<int, mixed> $args Already provided arguments.
     * @return array<int, mixed> The resolved arguments.
     * @throws Exception If a required argument cannot be resolved.
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
