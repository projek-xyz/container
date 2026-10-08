<?php

declare(strict_types=1);

namespace Projek\Container\Entry;

use Closure;
use Projek\Callable\Handler;
use Projek\Container\Entry;
use Projek\Container\EntryCollector;
use Projek\Container\InvalidArgumentException;
use Psr\Container\ContainerInterface;
use ReflectionFunction;
use ReflectionMethod;

/**
 * A closure, a function-name string, or an object with __invoke — one class
 * for all three (§5 row 1/3c): stored and invoked as-is, never re-instantiated.
 */
class CallableEntry extends Entry
{
    /**
     * @param  string|object  $factory  Closure, function name, or invokable object.
     *
     * @throws InvalidArgumentException When the factory is not a supported callable shape or takes parameters by reference.
     */
    public function __construct(string $id, public readonly string|object $factory, bool $auto = false)
    {
        if ($factory instanceof Closure) {
            $reflection = new ReflectionFunction($factory);
        } elseif (\is_object($factory)) {
            if (! \method_exists($factory, '__invoke')) {
                throw InvalidArgumentException::plainObjectNotAFactory($id, $factory);
            }

            $reflection = new ReflectionMethod($factory, '__invoke');
        } elseif (\function_exists($factory)) {
            $reflection = new ReflectionFunction($factory);
        } else {
            throw InvalidArgumentException::notAFunction($id, $factory);
        }

        foreach ($reflection->getParameters() as $parameter) {
            if ($parameter->isPassedByReference()) {
                throw InvalidArgumentException::byReferenceParam($id, $parameter->getName());
            }
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
