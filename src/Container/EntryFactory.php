<?php

declare(strict_types=1);

namespace Projek\Container;

use Psr\Container\ContainerInterface;

interface EntryFactory
{
    public function create(ContainerInterface $container): object;
}
