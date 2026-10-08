<?php

declare(strict_types=1);

namespace Projek\Container\Entry;

use Projek\Callable\Handler;
use Projek\Container\Entry;
use Projek\Container\EntryCollector;
use Projek\Container\EntryFactory;
use Psr\Container\ContainerInterface;
use ReflectionMethod;

/**
 * An EntryFactory instance — the explicit door for registering an
 * object instance: `new EntryFactory(fn () => $instance)`.
 *
 * @internal
 */
final class FactoryEntry extends Entry
{
    public function __construct(string $id, public readonly EntryFactory $factory, bool $auto = false)
    {
        [$parameters, $returnType] = self::extractMetadata(new ReflectionMethod($factory, 'create'));

        parent::__construct($id, $parameters, $returnType, $auto);
    }

    /**
     * {@inheritdoc}
     *
     * $args never reach create(): its signature is fixed by EntryFactory.
     */
    protected function produce(Handler $handler, ContainerInterface $container, array $args): mixed
    {
        return $this->factory->create($container);
    }

    /**
     * @assert-if-true EntryFactory $factory
     *
     * @phpstan-assert-if-true EntryFactory $factory
     */
    public static function isValid(mixed $factory): bool
    {
        return $factory instanceof EntryFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function extensionTarget(EntryCollector $entries): ?string
    {
        return 'object';
    }
}
