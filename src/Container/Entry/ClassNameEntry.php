<?php

declare(strict_types=1);

namespace Projek\Container\Entry;

use Projek\Callable\Handler;
use Projek\Callable\ResolverInterface;
use Projek\Container\Entry;
use Projek\Container\EntryCollector;
use Projek\Container\InvalidArgumentException;
use Psr\Container\ContainerInterface;
use ReflectionClass;

/**
 * An instantiable class-string: binds and constructs itself through the
 * container-supplied resolver (ResolverInterface::resolveInstance()) — never
 * through the Handler, so a bare class-string never reaches Handler::handle().
 *
 * @internal
 */
final class ClassNameEntry extends Entry
{
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
        // Fetched through the container on every build so a user-supplied
        // override flows; construction never reaches $handler.
        /** @var ResolverInterface $resolver */
        $resolver = $container->get(ResolverInterface::class);

        return $resolver->resolveInstance($this->factory, $args);
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
