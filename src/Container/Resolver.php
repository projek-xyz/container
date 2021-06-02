<?php

declare(strict_types=1);

namespace Projek\Container;

use Projek\Container;

/**
 * Container factory resolver class.
 *
 * An internal class mainly used for resolving and handling container factories.
 *
 * @package Projek\Container
 * @internal
 */
final class Resolver extends AbstractContainerAware
{
    /**
     * Create instance.
     *
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        $this->setContainer($container);
    }

    /**
     * Entry resolver.
     *
     * Ensure the given argument is a callable.
     *
     * @param string|object|callable|\Closure $entry
     * @param array $args
     * @return object|callable
     * @throws \Projek\Container\InvalidArgumentException
     * @throws \Projek\Container\Exception
     */
    public function resolve($entry, array $args = [])
    {
        if (\is_string($entry) && ! \function_exists($entry)) {
            $entry = false === \strpos($entry, '::')
                ? $this->createInstance($entry, $args)
                : \explode('::', $entry);
        }

        if (\is_object($entry)) {
            if ($entry instanceof ContainerAware && null === $entry->getContainer()) {
                $entry->setContainer($this->getContainer());
            }

            return $entry;
        }

        if (\is_array($entry)) {
            $entry[0] = $this->resolve($entry[0]);
        }

        if ($this->assertCallable($entry)) {
            return $entry;
        }

        throw new InvalidArgumentException(\sprintf('Cannot resolve invalid entry of %s', \gettype($entry)));
    }

    /**
     * Handle callable.
     *
     * @param callable $entry
     * @param array $args
     * @return mixed
     * @throws \Projek\Container\Exception
     */
    public function handle($entry, array $args = [])
    {
        // Returns the object if it was non-callable instance.
        if (! $this->assertCallable($entry) && \is_object($entry)) {
            return $entry;
        }

        $params = [];
        $ref = $this->createReflection($entry);
        $caller = $ref->getName();

        if ($isMethod = ($ref instanceof \ReflectionMethod)) {
            $caller = $ref->getDeclaringClass()->getName() . '::' . $ref->getName();
            $params[] = $ref->isStatic() && ! \is_object($entry[0]) ? null : $entry[0];
        }

        // If it was internal method resolve its params as a closure.
        // @link https://bugs.php.net/bug.php?id=50798
        $toResolve = $isMethod && $ref->getName() === '__invoke'
            ? new \ReflectionFunction($ref->getClosure($entry[0]))
            : $ref;

        try {
            $params[] = $this->resolveArgs($toResolve, $args);

            return $ref->invokeArgs(...$params);
        } catch (Exception $err) {
            throw new Exception($caller . '(): ' . $err->getMessage(), $err->getPrevious());
        }
    }

    /**
     * Create an instance of $className.
     *
     * @param string $className
     * @return object
     * @throws \Projek\Container\Exception
     *  When $className is not instantiable or its constructor depends on non-exists container entry.
     */
    private function createInstance(string $className, array $args = [])
    {
        if ($this->getContainer()->has($className)) {
            return $this->getContainer($className);
        }

        if (! \class_exists($className)) {
            throw new Exception(
                \sprintf('Cannot resolve an entry or class named "%s" of non-exists', $className)
            );
        }

        try {
            $ref = new \ReflectionClass($className);
            $constructor = $ref->getConstructor();

            if (! $constructor) {
                return $ref->newInstance();
            }

            return $ref->newInstanceArgs(
                $this->resolveArgs($constructor, $ref->hasMethod('__invoke') ? [] : $args)
            );
        } catch (Exception $err) {
            throw new Exception($className . '::__construct(): ' . $err->getMessage(), $err->getPrevious());
        } catch (\Throwable $err) {
            throw new Exception($err->getMessage(), $err);
        }
    }

    /**
     * Instance resolver.
     *
     * @param callable $callable
     * @return \ReflectionMethod|\ReflectionFunction|null
     * @throws \Projek\Container\Exception
     * @throws \Projek\Container\InvalidArgumentException
     */
    private function createReflection($callable)
    {
        if (\is_string($callable)) {
            if (false === \strpos($callable, '::')) {
                return new \ReflectionFunction($callable);
            }

            $callable = \explode('::', $callable);
        }

        $ref = new \ReflectionMethod($callable[0], $callable[1]);

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
     * @param array<mixed> $args
     * @return array
     * @throws \Projek\Container\Exception
     */
    private function resolveArgs($reflection, array $args = []): array
    {
        foreach ($reflection->getParameters() as $param) {
            // Just skip if parameter already provided.
            if (\array_key_exists($position = $param->getPosition(), $args)) {
                continue;
            }

            $type = $param->getType();

            try {
                $args[$position] = $this->getContainer(
                    ($type && ! $type->isBuiltin() ? $type : $param)->getName()
                );
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

    /**
     * Assert callable $instance.
     *
     * @param callable $instance
     * @return bool
     * @throws \Projek\Container\InvalidArgumentException
     *  When $instance is an array but the callable method not exists.
     */
    private function assertCallable(&$instance): bool
    {
        if (\is_object($instance) && \method_exists($instance, '__invoke')) {
            $instance = [$instance, '__invoke'];
        }

        if (\is_array($instance) && ! \method_exists(...$instance)) {
            throw new InvalidArgumentException(
                \sprintf('Method %s::%s() is not exists', \get_class($instance[0]), $instance[1])
            );
        }

        return \is_callable($instance);
    }
}
