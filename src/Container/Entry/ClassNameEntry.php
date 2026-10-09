<?php

declare(strict_types=1);

namespace Projek\Container\Entry;

use Projek\Callable\Handler;
use Projek\Container\Entry;
use Projek\Container\EntryCollector;
use Projek\Container\InvalidArgumentException;
use Psr\Container\ContainerInterface;
use ReflectionClass;

/**
 * An instantiable class-string: binds and constructs itself through the
 * handler-bound resolver (Handler::$resolver, callable >= 0.4.1) — a bare
 * class-string is always built, never invoked: it never reaches Handler::handle().
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
     * Construction delegates to the handler-bound resolver through Handler's
     * public readonly $resolver property (callable >= 0.4.1): it never reaches
     * Handler::handle() and never queries container entries.
     */
    protected function produce(Handler $handler, ContainerInterface $container, array $args): mixed
    {
        return $handler->resolver->resolveInstance($this->factory, $args);
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
