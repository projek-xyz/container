<?php

declare(strict_types=1);

namespace Stubs;

use Projek\Callable\ResolverInterface;
use ReflectionClass;
use ReflectionParameter;

/**
 * Recording ResolverInterface implementation: proves ClassNameEntry fetches
 * the resolver from the container per build (user overrides flow) and that
 * all construction goes through resolveInstance().
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
     *
     * Records the parameter names the caller did not provide (those fall back
     * to resolveParameter()), then delegates the real binding to the inner
     * resolver.
     */
    public function resolveArguments(array $parameters, array $provided): array
    {
        $positional = 0;
        $named = [];

        foreach ($provided as $key => $value) {
            if (\is_int($key)) {
                $positional++;
            } else {
                $named[$key] = true;
            }
        }

        foreach ($parameters as $param) {
            if ($param->isVariadic()) {
                break;
            }

            if ($param->getPosition() >= $positional && ! isset($named[$param->getName()])) {
                $this->parameters[] = $param->getName();
            }
        }

        return $this->inner->resolveArguments($parameters, $provided);
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
     *
     * Construction routes through this spy (never the inner resolver's own
     * resolveInstance()) so the fallback parameter names land in $parameters;
     * the binding itself still delegates to the inner resolver.
     */
    public function resolveInstance(string $entry, array $args = []): object
    {
        $this->instances[] = $entry;

        $reflection = new ReflectionClass($entry);
        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            return $this->inner->resolveInstance($entry, $args);
        }

        $bound = $this->resolveArguments($constructor->getParameters(), $args);

        return $reflection->newInstanceArgs($bound);
    }
}
