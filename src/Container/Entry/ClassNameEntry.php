<?php

declare(strict_types=1);

namespace Projek\Container\Entry;

use Projek\Callable\Handler;
use Projek\Callable\ParametersHelper;
use Projek\Callable\ResolverInterface;
use Projek\Callable\UnresolvableCallableException;
use Projek\Container\Entry;
use Projek\Container\EntryCollector;
use Projek\Container\InvalidArgumentException;
use Psr\Container\ContainerInterface;
use ReflectionClass;

/**
 * An instantiable class-string (§5 row 3b): binds and constructs itself through
 * the package's shared ParametersHelper trait — never through the Handler, so
 * a bare class-string never reaches Handler::handle().
 */
class ClassNameEntry extends Entry
{
    use ParametersHelper;

    /**
     * @param  string  $factory  class-string of an instantiable class (dispatch guarantees buildability; re-asserted defensively).
     *
     * @throws InvalidArgumentException When the class does not exist, is not instantiable, or its constructor takes parameters by reference.
     */
    public function __construct(string $id, public readonly string $factory, bool $auto = false)
    {
        if (! \class_exists($factory) || ! (new ReflectionClass($factory))->isInstantiable()) {
            throw new InvalidArgumentException(\sprintf(
                'Cannot register entry "%s": "%s" is not an instantiable class.',
                $id,
                $factory
            ));
        }

        $reflection = new ReflectionClass($factory);
        $constructor = $reflection->getConstructor();
        $parameters = [];

        if ($constructor !== null) {
            foreach ($constructor->getParameters() as $parameter) {
                if ($parameter->isPassedByReference()) {
                    throw InvalidArgumentException::byReferenceParam($id, $parameter->getName());
                }
            }

            [$parameters] = self::extractMetadata($constructor);
        }

        parent::__construct($id, $parameters, $factory, $auto);
    }

    /**
     * {@inheritdoc}
     *
     * The resolver is fetched through the container on every build so a
     * user-supplied override flows; class construction never reaches $handler.
     */
    protected function produce(Handler $handler, ContainerInterface $container, array $args): mixed
    {
        $this->resolver = $container->get(ResolverInterface::class);

        return $this->instantiate($this->factory, $args);
    }

    /**
     * Bind and construct one instance (§5): empty $args delegates to the
     * package's construction path; otherwise the constructor is bound directly
     * through the composed ParametersHelper — no container-side binding logic.
     *
     * @param  array<mixed>  $args
     *
     * @throws UnresolvableCallableException When the class is not instantiable (defensive).
     */
    private function instantiate(string $class, array $args): object
    {
        if ($args === []) {
            return $this->resolver->resolveInstance($class);
        }

        $reflection = new ReflectionClass($class);

        if (! $reflection->isInstantiable()) {
            throw UnresolvableCallableException::notInstantiable($class);
        }

        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            return $reflection->newInstance();
        }

        return $reflection->newInstanceArgs(
            $this->buildArguments($constructor->getParameters(), $args)
        );
    }

    /**
     * {@inheritdoc}
     */
    public function extensionTarget(EntryCollector $entries): ?string
    {
        return $this->factory;
    }
}
