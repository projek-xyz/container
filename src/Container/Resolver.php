<?php

declare(strict_types=1);

namespace Projek\Container;

use Psr\Container\ContainerInterface;

class Resolver implements ContainerAwareInterface
{
    use ContainerAware;

    /**
     * Create new instance.
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
        $isInternalMethod = $isMethod && $instance[1] === '__invoke';
        $reflector = $isMethod
            ? new \ReflectionMethod($instance[0], $instance[1])
            : new \ReflectionFunction($instance);

        if ($isMethod) {
            $obj = is_object($instance[0]) ? $instance[0] : null;

            if (! $reflector->isStatic() && is_string($instance[0])) {
                $obj = $this->createInstance($instance[0]);
            }

            $params[] = $obj;
        }

        // If it was internal method resolve its params as a closure.
        // @link https://bugs.php.net/bug.php?id=50798
        $toResolve = $isInternalMethod
            ? new \ReflectionFunction($reflector->getClosure($instance[0]))
            : $reflector;

        $params[] = $this->resolveArgs($toResolve, $args);

        return $reflector->invokeArgs(...$params);
    }

    /**
     * Instance resolver.
     *
     * @param string|object|callable|\Closure $toResolve
     * @return object
     * @throws Exception When $toResolve is neither string of class name, instance
     *                   of \Closure, object of class nor a callable.
     */
    public function resolve($toResolve)
    {
        switch (true) {
            case is_object($toResolve):
                return $this->injectContainer($toResolve);
            case is_string($toResolve) && class_exists($toResolve):
                return $this->createInstance($toResolve);
            case is_string($toResolve) && $this->getContainer()->has($toResolve):
            case $toResolve instanceof \Closure:
            case is_callable($toResolve):
                return $toResolve;
            default:
                throw new Exception(sprintf(
                    'Couldn\'t resolve "%s" as an instance.',
                    ! is_string($toResolve) ? gettype($toResolve) : $toResolve
                ));
        }
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
        if ($this->getContainer()->has($className)) {
            return $this->getContainer($className);
        }

        $reflector = new \ReflectionClass($className);

        if (! $reflector->isInstantiable()) {
            throw new Exception(sprintf('Target "%s" is not instantiable.', $className));
        }

        $args = ($constructor = $reflector->getConstructor()) ? $this->resolveArgs($constructor) : [];

        return $this->injectContainer($reflector->newInstanceArgs($args));
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
                $args[$param->getPosition()] = $this->getContainer(
                    $this->getArgsType($param)->getName()
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
     * Determine parameter type.
     *
     * @param \ReflectionParameter $param
     * @return \ReflectionParameter
     */
    private function getArgsType(\ReflectionParameter $param)
    {
        $type = $param->getType();

        return $type && ! $type->isBuiltin() ? $type : $param;
    }

    /**
     * Assert $instance is callable.
     *
     * @param callable $instance
     * @return bool
     * @throws BadMethodCallException When $instance is an array but the callable
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
            throw new BadMethodCallException($instance);
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
