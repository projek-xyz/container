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
 * An EntryFactory instance (§5 row 2) — the explicit door for registering an
 * object instance: `new EntryFactory(fn () => $instance)`.
 */
class FactoryEntry extends Entry
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
     * {@inheritdoc}
     */
    public function extensionTarget(EntryCollector $entries): ?string
    {
        return 'object';
    }

    /**
     * {@inheritdoc}
     */
    public function getFactory(): EntryFactory
    {
        return $this->factory;
    }
}
