<?php

declare(strict_types=1);

namespace Projek\Container;

use Projek\Container;

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
     * Handle callable.
     *
     * @param callable $instance
     * @param array $args
     * @return mixed
     */
    public function handle($instance, array $args = [])
    {
        if (! $this->assertCallable($instance) && \is_object($instance)) {
            // Returns the object if it was non-callable instance.
            return $instance;
        }

        $params = [];
        $ref = $this->createReflection($instance);

        if ($isMethod = ($ref instanceof \ReflectionMethod)) {
            $params[] = $ref->isStatic() && ! \is_object($instance[0]) ? null : $instance[0];
        }

        // If it was internal method resolve its params as a closure.
        // @link https://bugs.php.net/bug.php?id=50798
        $toResolve = $isMethod && $ref->getName() === '__invoke'
            ? new \ReflectionFunction($ref->getClosure($instance[0]))
            : $ref;

        $params[] = $this->resolveArgs($toResolve, $args);

        return $ref->invokeArgs(...$params);
    }

    /**
     * Instance resolver.
     *
     * @param string|object|callable|\Closure $toResolve
     * @return object|callable
     * @throws Exception\UnresolvableException
     */
    public function resolve($toResolve)
    {
        if (\is_string($toResolve) && ! \function_exists($toResolve)) {
            $toResolve = false === \strpos($toResolve, '::')
                ? $this->createInstance($toResolve)
                : \explode('::', $toResolve);
        }

        if (\is_object($toResolve)) {
            if ($toResolve instanceof ContainerAware && null === $toResolve->getContainer()) {
                $toResolve->setContainer($this->getContainer());
            }

            return $toResolve;
        }

        if (\is_array($toResolve)) {
            $toResolve[0] = $this->resolve($toResolve[0]);
        }

        if ($this->assertCallable($toResolve)) {
            return $toResolve;
        }

        throw new Exception\UnresolvableException($toResolve);
    }

    /**
     * Create an instance of $className.
     *
     * @param string $className
     * @return object
     * @throws Exception When $className is not instantiable.
     */
    private function createInstance(string $className)
    {
        if ($this->getContainer()->has($className)) {
            return $this->getContainer($className);
        }

        try {
            $ref = new \ReflectionClass($className);
            $args = ($constructor = $ref->getConstructor()) ? $this->resolveArgs($constructor) : [];

            return $ref->newInstanceArgs($args);
        } catch (Exception\UnresolvableException $err) {
            throw $err;
        } catch (\Throwable $err) {
            throw new Exception\UnresolvableException($err);
        }
    }

    /**
     * Instance resolver.
     *
     * @param callable $callable
     * @return \ReflectionMethod|\ReflectionFunction|null
     * @throws Exception\UnresolvableException
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
            throw new Exception(\sprintf(
                'Non-static method %s should not be called statically',
                \join('::', $callable)
            ));
        }

        return $ref;
    }

    /**
     * Callable argumetns resolver.
     *
     * @param \ReflectionFunctionAbstract $reflection
     * @param array<mixed> $args
     * @return array
     */
    private function resolveArgs($reflection, array $args = []): array
    {
        $container = $this->getContainer();

        foreach ($reflection->getParameters() as $param) {
            // Just skip if parameter already provided.
            if (\array_key_exists($position = $param->getPosition(), $args)) {
                continue;
            }

            $type = $param->getType();
            $dependency = ($type && ! $type->isBuiltin() ? $type : $param)->getName();

            try {
                $args[$position] = $container->get($dependency);
            } catch (Exception\NotFoundException $err) {
                if (! $param->isOptional()) {
                    throw new Exception\UnresolvableException($err);
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
     * @throws Exception\UnresolvableException When $instance is an array but the callable
     *                                         method not exists.
     */
    private function assertCallable(&$instance): bool
    {
        if (\is_object($instance) && \method_exists($instance, '__invoke')) {
            $instance = [$instance, '__invoke'];
        }

        if (\is_array($instance) && ! \method_exists(...$instance)) {
            throw new Exception\UnresolvableException($instance);
        }

        return \is_callable($instance);
    }
}
