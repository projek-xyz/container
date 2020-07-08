<?php

declare(strict_types=1);

namespace Projek\Container;

use Closure;
// use Psr\Container\ContainerInterface;
use ReflectionClass;
use ReflectionException;
use ReflectionFunction;
use ReflectionMethod;

class Resolver implements ContainerAwareInterface
{
    use ContainerAware;

    /**
     * Create new instance.
     *
     * @param ContainerInterface $container
     */
    final public function __construct(ContainerInterface $container)
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
        $isInternalMethod = $isMethod && $instance[1] === '__invoke';
        $reflector = $isMethod
            ? new ReflectionMethod($instance[0], $instance[1])
            : new ReflectionFunction($instance);

        if ($isMethod) {
            $params[] = is_object($instance[0]) ? $instance[0] : null;
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
     * @param string|object|callable|Closure $toResolve
     * @return object|callable
     * @throws UnresolvableException
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

        try {
            if ($this->assertCallable($toResolve)) {
                return $toResolve;
            }
        } catch (UnresolvableException $err) {
            // do nothing
        }

        throw new UnresolvableException($toResolve);
    }

    /**
     * Create an instance of $className.
     *
     * @param string $className
     * @return object
     * @throws Exception When $className is not instantiable.
     */
    protected function createInstance(string $className)
    {
        if ($this->getContainer()->has($className)) {
            return $this->getContainer($className);
        }

        try {
            $reflector = new ReflectionClass($className);
        } catch (ReflectionException $err) {
            throw new UnresolvableException($className, $err);
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
    protected function resolveArgs($reflection, array $args = []): array
    {
        foreach ($reflection->getParameters() as $param) {
            $position = $param->getPosition();

            // Just skip if parameter already provided.
            if (array_key_exists($position, $args)) {
                continue;
            }

            try {
                $type = $param->getType();
                $args[$position] = $this->getContainer(
                    ($type && ! $type->isBuiltin() ? $type : $param)->getName()
                );
            } catch (NotFoundException $e) {
                if (! $param->isOptional()) {
                    throw $e;
                }

                $args[$position] = $param->getDefaultValue();
            }
        }

        return $args;
    }

    /**
     * Assert $instance is callable.
     *
     * @param callable $instance
     * @return bool
     * @throws UnresolvableException When $instance is an array but the callable
     *                                 method not exists.
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
                $instance[0] = $this->createInstance($instance[0]);
            }

            if (! method_exists(...$instance)) {
                throw new UnresolvableException([get_class($instance[0]), $instance[1]]);
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
        if ($instance instanceof ContainerAwareInterface && null === $instance->getContainer()) {
            $instance->setContainer($this->getContainer());
        }

        return $instance;
    }
}
