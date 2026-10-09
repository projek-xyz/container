<?php

declare(strict_types=1);

namespace Projek\Container\Entry;

use Projek\Callable\Handler;
use Projek\Callable\ParametersHelper;
use Projek\Callable\ResolverInterface;
use Projek\Container\Entry;
use Projek\Container\EntryCollector;
use Projek\Container\InvalidArgumentException;
use Psr\Container\ContainerInterface;
use ReflectionClass;

/**
 * An instantiable class-string: binds and constructs itself through
 * the package's shared ParametersHelper trait — never through the Handler, so
 * a bare class-string never reaches Handler::handle().
 *
 * @internal
 */
final class ClassNameEntry extends Entry
{
    use ParametersHelper;

    /**
     * @param  class-string  $factory  class-string of an instantiable class (dispatch guarantees buildability; re-asserted defensively).
     *
     * @throws InvalidArgumentException When the class does not exist, is not instantiable, or its constructor takes parameters by reference.
     */
    public function __construct(string $id, public readonly string $factory, bool $auto = false)
    {
        if (! self::isValid($factory)) {
            throw InvalidArgumentException::notInstantiable($id, $factory);
        }

        $reflection = new ReflectionClass($factory);
        $parameters = [];

        if ($constructor = $reflection->getConstructor()) {
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

        if ($args === []) {
            $this->resolver->resolveInstance($this->factory);
        }

        // Bind and construct one instance: empty $args delegates to the package's
        // construction path; otherwise the constructor is bound directly through
        // the composed `ParametersHelper` — no container-side binding logic.
        $reflection = new ReflectionClass($this->factory);

        if (($constructor = $reflection->getConstructor()) === null) {
            return $reflection->newInstance();
        }

        return $reflection->newInstanceArgs(
            $this->buildArguments($constructor->getParameters(), $args)
        );
    }

    /**
     * @assert-if-true class-string $factory
     *
     * @phpstan-assert-if-true class-string $factory
     */
    public static function isValid(mixed $factory): bool
    {
        return \is_string($factory) && \class_exists($factory)
            && (new ReflectionClass($factory))->isInstantiable();
    }

    /**
     * {@inheritdoc}
     */
    public function extensionTarget(EntryCollector $entries): ?string
    {
        return $this->factory;
    }
}
