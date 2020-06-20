<?php

declare(strict_types=1);

namespace Projek\Container;

use Psr\Container\ContainerInterface;
use ReflectionFunction;

class Resolver
{
    /**
     * PSR 11 Container Instance.
     *
     * @var ContainerInterface
     */
    private $container;

    /**
     * Create new instance.
     *
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
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
        if (! $this->assertCallable($instance)) {
            return $instance;
        }

        $params = [];
        $isMethod = is_array($instance);
        $isInternalMethod = $isMethod && $instance[1] === '__invoke';
        $reflector = $isMethod
            ? new \ReflectionMethod($instance[0], $instance[1])
            : new \ReflectionFunction($instance);

        if ($isMethod) {
            $params[] = $instance[0];
        }

        // If it was internal method resolve its params as a closure.
        // @link https://bugs.php.net/bug.php?id=50798
        $toResolve = $isInternalMethod
            ? new ReflectionFunction($reflector->getClosure($instance[0]))
            : $reflector;

        $params[] = $this->resolveArgs($toResolve, $args);

        return $reflector->invokeArgs(...$params);
    }

    /**
     * Instance resolver.
     *
     * @param string|object|callable|\Closure $concrete
     * @return mixed
     * @throws Exception When $concrete is neither string of class name, instance
     *                   of \Closure, object of class nor a callable.
     */
    public function resolve($concrete)
    {
        if (is_string($concrete) && class_exists($concrete)) {
            return $this->createInstance($concrete);
        }

        if (is_string($concrete) && $this->container->has($concrete)) {
            return $concrete;
        }

        if ($concrete instanceof \Closure || is_object($concrete) || is_callable($concrete)) {
            return $concrete;
        }

        throw Exception::unresolvable($concrete);
    }

    /**
     * Create an instance of $className.
     *
     * @param string $className
     * @return array
     * @throws Exception When $className is not instantiable.
     */
    protected function createInstance(string $className)
    {
        if ($this->container->has($className)) {
            return $this->container->get($className);
        }

        $reflector = new \ReflectionClass($className);

        if (! $reflector->isInstantiable()) {
            throw Exception::notInstantiable($className);
        }

        if ($constructor = $reflector->getConstructor()) {
            return $reflector->newInstanceArgs($this->resolveArgs($constructor));
        }

        return $reflector->newInstance();
    }

    /**
     * Callable argumetns resolver.
     *
     * @param \ReflectionFunctionAbstract $callable
     * @param array<mixed> $args
     * @return array
     */
    protected function resolveArgs(\ReflectionFunctionAbstract $callable, array $args = []): array
    {
        foreach ($callable->getParameters() as $param) {
            // Just skip if parameter already provided.
            if (array_key_exists($param->getPosition(), $args)) {
                continue;
            }

            try {
                $args[$param->getPosition()] = $this->container->get(
                    ($class = $param->getClass()) ? $class->getName() : $param->getName()
                );
            } catch (NotFoundException $e) {
                if (! $param->isOptional()) {
                    throw $e;
                }

                $args[$param->getPosition()] = $param->getDefaultValue();
            }
        }

        return $args;
    }

    /**
     * Assert $instance is callable.
     *
     * @param callable $instance
     * @return bool
     * @throws \BadMethodCallException When $instance is an array but the callable
     *                                 method not exists.
     */
    private function assertCallable(&$instance): bool
    {
        if (is_string($instance) && false !== strpos($instance, '::')) {
            $instance = explode('::', $instance);
        } elseif (is_object($instance) && method_exists($instance, '__invoke')) {
            $instance = [$instance, '__invoke'];
        }

        if (is_array($instance) && ! method_exists($instance[0], $instance[1])) {
            throw new \BadMethodCallException(
                sprintf('Call to undefined method %s::%s()', get_class($instance[0]), $instance[1])
            );
        }

        return is_callable($instance);
    }
}
