<?php

declare(strict_types=1);

namespace Stubs;

use Projek\Callable\ResolverInterface;
use ReflectionParameter;

/**
 * Recording ResolverInterface implementation: proves ClassNameEntry fetches
 * the resolver from the container per build (user overrides flow) and that
 * empty-args construction delegates to resolveInstance().
 */
class SpyResolver implements ResolverInterface
{
    /**
     * @var list<string> Entries passed to resolveInstance().
     */
    public array $instances = [];

    /**
     * @var list<string> Parameter names passed to resolveParameter().
     */
    public array $parameters = [];

    public function __construct(private ResolverInterface $inner)
    {
        // .
    }

    /**
     * {@inheritdoc}
     */
    public function resolveCallable($callable): callable
    {
        return $this->inner->resolveCallable($callable);
    }

    /**
     * {@inheritdoc}
     */
    public function resolveParameter(ReflectionParameter $param): mixed
    {
        $this->parameters[] = $param->getName();

        return $this->inner->resolveParameter($param);
    }

    /**
     * {@inheritdoc}
     */
    public function resolveInstance(string $entry): object
    {
        $this->instances[] = $entry;

        return $this->inner->resolveInstance($entry);
    }
}
