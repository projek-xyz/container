<?php

declare(strict_types=1);

namespace Projek\Container;

use Closure;
use ReflectionClass;
use ReflectionException;
use ReflectionFunction;
use ReflectionMethod;

final class Resolver extends AbstractContainerAware
{
    /**
     * Create instance.
     *
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
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
        if (! $this->assertCallable($instance)) {
            return $instance;
        }

        $params = [];
        $isMethod = is_array($instance);
        $reflector = $isMethod
            ? new ReflectionMethod($instance[0], $instance[1])
            : new ReflectionFunction($instance);

        if ($isMethod) {
            $params[] = is_object($instance[0]) ? $instance[0] : null;
        }

        // If it was internal method resolve its params as a closure.
        // @link https://bugs.php.net/bug.php?id=50798
        $toResolve = $isMethod && $instance[1] === '__invoke'
            ? new ReflectionFunction($reflector->getClosure($instance[0]))
            : $reflector;

        $params[] = $this->resolveArgs($toResolve, $args);

        return $reflector->invokeArgs(...$params);
    }

    /**
     * Instance resolver.
     *
     * @param string|object|callable|Closure $toResolve
     * @return object|callable
     * @throws Exception\UnresolvableException
     */
    public function resolve($toResolve)
    {
        if (is_string($toResolve) && ! function_exists($toResolve)) {
            if (false === strpos($toResolve, '::')) {
                return $this->createInstance($toResolve);
            }

            $toResolve = explode('::', $toResolve);
        }

        if (is_object($toResolve)) {
            return $toResolve instanceof Closure ? $toResolve : $this->injectContainer($toResolve);
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
            $reflector = new ReflectionClass($className);
        } catch (ReflectionException $err) {
            throw new Exception\UnresolvableException($className, $err);
        }

        if (! $reflector->isInstantiable()) {
            throw new Exception(sprintf('Target "%s" is not instantiable.', $className));
        }

        $args = ($constructor = $reflector->getConstructor()) ? $this->resolveArgs($constructor) : [];

        return $this->injectContainer($reflector->newInstanceArgs($args));
    }

    /**
     * Callable argumetns resolver.
     *
     * @param ReflectionMethod|ReflectionFunction $reflection
     * @param array<mixed> $args
     * @return array
     */
    private function resolveArgs($reflection, array $args = []): array
    {
        foreach ($reflection->getParameters() as $param) {
            $position = $param->getPosition();

            // Just skip if parameter already provided.
            if (array_key_exists($position, $args)) {
                continue;
            }

            try {
                /** @var ReflectionNamedType $type */
                $type = $param->getType();
                $args[$position] = $this->getContainer(
                    ($type && ! $type->isBuiltin() ? $type : $param)->getName()
                );
            } catch (Exception\NotFoundException $e) {
                if (! $param->isOptional()) {
                    throw $e;
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
        if (is_string($instance) && false !== strpos($instance, '::')) {
            $instance = explode('::', $instance);
        } elseif (is_object($instance) && method_exists($instance, '__invoke')) {
            $instance = [$instance, '__invoke'];
        }

        if (is_array($instance)) {
            if (is_string($instance[0])) {
                try {
                    $instance[0] = $this->createInstance($instance[0]);
                } catch (Exception\UnresolvableException $err) {
                    throw new Exception\UnresolvableException($instance, $err);
                }
            }

            if (! method_exists(...$instance)) {
                throw new Exception\UnresolvableException($instance);
            }
        }

        return is_callable($instance);
    }

    /**
     * Injecting Container instance if $instance implements ContainerAwareInterface.
     *
     * @param object $instance
     * @return object
     */
    private function injectContainer($instance)
    {
        if (
            $instance instanceof ContainerAwareInterface
            && ! $instance->getContainer() instanceof ContainerInterface
        ) {
            $instance->setContainer($this->getContainer());
        }

        return $instance;
    }
}
