<?php

declare(strict_types=1);

namespace Projek\Container\Entry;

use Projek\Callable\Handler;
use Projek\Container\Entry;
use Projek\Container\EntryCollector;
use Psr\Container\ContainerInterface;

/**
 * Any other string — including non-buildable type symbols whose
 * target pre-exists. The target check belongs to Container::set(), not here.
 */
class AliasEntry extends Entry
{
    /**
     * @param  string  $factory  Registered id of the alias target.
     */
    public function __construct(string $id, public readonly string $factory, bool $auto = false)
    {
        parent::__construct($id, [], null, $auto);
    }

    /**
     * {@inheritdoc}
     *
     * get() path only: make() unwraps the alias chain first, so $args
     * never reach this branch with a non-empty value.
     */
    protected function produce(Handler $handler, ContainerInterface $container, array $args): mixed
    {
        return $container->get($this->factory);
    }

    /**
     * {@inheritdoc}
     *
     * Follow the alias chain through the collector — acyclic by construction
     * (each id registers once and a target must pre-exist); a dead end is not derivable.
     */
    public function extensionTarget(EntryCollector $entries): ?string
    {
        $target = $this->factory;

        while (isset($entries[$target])) {
            $entry = $entries[$target];

            if (! $entry instanceof self) {
                return $entry->extensionTarget($entries);
            }

            $target = $entry->factory;
        }

        return null;
    }
}
