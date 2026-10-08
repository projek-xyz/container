<?php

declare(strict_types=1);

namespace Projek\Container\Entry;

use Projek\Callable\Handler;
use Projek\Container\Entry;
use Projek\Container\EntryCollector;
use Projek\Container\InvalidArgumentException;
use Psr\Container\ContainerInterface;
use ReflectionMethod;

/**
 * A `Class::method` string or [class, method] pair: class and method are
 * validated beside the class they describe.
 *
 * @internal
 */
final class MethodPairEntry extends Entry
{
    /**
     * @template T of object
     *
     * @param  array{class-string<T>|T,string}|string  $factory
     *
     * @throws InvalidArgumentException When the pair shape, class, method, or method visibility is invalid.
     */
    public function __construct(string $id, public readonly string|array $factory, bool $auto = false)
    {
        if (\is_string($factory)) {
            if (! \str_contains($factory, '::')) {
                throw InvalidArgumentException::invalidMethodPair($id, $factory);
            }

            [$class, $method] = \explode('::', $factory, 2);
        } else {
            if (\count($factory) !== 2) {
                throw InvalidArgumentException::invalidMethodPair($id, $factory);
            }

            [$class, $method] = [$factory[0], $factory[1]];
        }

        if (\is_object($class)) {
            $class = \get_class($class);
        }

        if (! \is_string($class) || ! \class_exists($class)) {
            throw InvalidArgumentException::pairClassNotFound($id, $class);
        }

        if (! \is_string($method) || ! \method_exists($class, $method)) {
            throw InvalidArgumentException::pairMethodNotFound($id, $class, $method);
        }

        $reflection = new ReflectionMethod($class, $method);

        if (! $reflection->isPublic()) {
            throw InvalidArgumentException::pairMethodNotPublic($id, $class, $method);
        }

        [$parameters, $returnType] = self::extractMetadata($reflection);

        parent::__construct($id, $parameters, $returnType, $auto);
    }

    /**
     * {@inheritdoc}
     */
    protected function produce(Handler $handler, ContainerInterface $container, array $args): mixed
    {
        return $handler->handle($this->factory, $args);
    }

    /**
     * @assert-if-true array{class-string|object,string}|string $factory
     *
     * @phpstan-assert-if-true array{class-string|object,string}|string $factory
     *
     * @psalm-assert-if-true array{class-string|object,string}|string $factory
     */
    public static function isValid(mixed $factory): bool
    {
        return (\is_string($factory) && \str_contains($factory, '::'))
            || (\is_array($factory) && count($factory) === 2);
    }

    /**
     * {@inheritdoc}
     */
    public function extensionTarget(EntryCollector $entries): ?string
    {
        return self::namedClassType($this->returnType);
    }
}
