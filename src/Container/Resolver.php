<?php

namespace Projek\Container;

use Psr\Container\ContainerInterface;

class Resolver
{
    /**
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
     * @param callable $callable
     * @return mixed
     */
    public function handle(callable $instance)
    {
        if (is_string($instance) && false !== strpos($instance, '::')) {
            $instance = explode('::', $instance);
        }

        $args = [];
        $reflector = ($isMethod = is_array($instance))
            ? new \ReflectionMethod($instance[0], $instance[1])
            : new \ReflectionFunction($instance);

        if ($isMethod) {
            $args[] = is_object($instance[0]) ? $instance[0] : null;
        }

        $args[] = $this->resolveArgs($reflector);

        return $reflector->invokeArgs(...$args);
    }

    /**
     * Instance resolver.
     *
     * @param string|object|callable|\Closure $concrete
     * @return mixed
     */
    public function resolve($concrete)
    {
        if (is_string($concrete) && class_exists($concrete)) {
            $ref = new \ReflectionClass($concrete);

            if (! $ref->isInstantiable()) {
                throw Exception::notInstantiable($concrete);
            }

            if ($constructor = $ref->getConstructor()) {
                return $ref->newInstanceArgs($this->resolveArgs($constructor));
            }

            return $ref->newInstance();
        }

        if ($concrete instanceof \Closure) {
            return $concrete->bindTo($this->container);
        }

        if (is_object($concrete) || is_callable($concrete)) {
            return $concrete;
        }

        throw Exception::unresolvable($concrete);
    }

    /**
     * Callable argumetns resolver
     *
     * @param \ReflectionFunctionAbstract $callable
     * @param array $params
     * @return array
     */
    protected function resolveArgs(\ReflectionFunctionAbstract $callable) : array
    {
        $args = [];

        foreach ($callable->getParameters() as $param) {
            try {
                $value = $this->container->get(
                    ($class = $param->getClass()) ? $class->getName() : $param->getName()
                );
            } catch (NotFoundException $e) {
                if (! $param->isOptional()) {
                    throw $e;
                }

                $value = $param->getDefaultValue();
            }

            $args[$param->getPosition()] = $value;
        }

        return $args;
    }
}
