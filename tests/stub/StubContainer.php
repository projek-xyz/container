<?php

declare(strict_types=1);

namespace Stubs;

use Projek\Container\NotFoundException;
use Psr\Container\ContainerInterface;

/**
 * Minimal PSR-11 container backed by an array, for driving Handler/Resolver
 * wiring inside the Entry specs.
 */
class StubContainer implements ContainerInterface
{
    /**
     * @param  array<string, mixed>  $entries
     */
    public function __construct(public array $entries = [])
    {
        // .
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $id): mixed
    {
        if (! \array_key_exists($id, $this->entries)) {
            throw new NotFoundException($id);
        }

        return $this->entries[$id];
    }

    /**
     * {@inheritdoc}
     */
    public function has(string $id): bool
    {
        return \array_key_exists($id, $this->entries);
    }
}
