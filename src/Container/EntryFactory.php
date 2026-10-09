<?php

declare(strict_types=1);

namespace Projek\Container;

use Psr\Container\ContainerInterface;

interface EntryFactory
{
    /**
     * Produce the entry's instance from the container it is being built for.
     *
     * @param  ContainerInterface  $container  The resolving container — factories wire their dependencies from it.
     */
    public function create(ContainerInterface $container): object;
}
