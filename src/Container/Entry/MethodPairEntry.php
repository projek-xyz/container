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
 * A `Class::method` string, or a 2-element pair `[class-string|object, method]`
 * (§5 rows 3a/4): class and method are validated beside the class they describe.
 */
class MethodPairEntry extends Entry
{
    /**
     * @param  string|array{class-string|object, string}  $factory
     *
     * @throws InvalidArgumentException When the pair shape, class, method, or method visibility is invalid.
     */
    public function __construct(string $id, public readonly string|array $factory, bool $auto = false)
    {
        if (\is_string($factory)) {
            if (! \str_contains($factory, '::')) {
                throw InvalidArgumentException::notClassMethodString($id, $factory);
            }

            [$class, $method] = \explode('::', $factory, 2);
        } else {
            if (\count($factory) !== 2 || ! isset($factory[0], $factory[1])) {
                throw InvalidArgumentException::invalidPairSize($id);
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
     * {@inheritdoc}
     */
    public function extensionTarget(EntryCollector $entries): ?string
    {
        return self::namedClassType($this->returnType);
    }
}
