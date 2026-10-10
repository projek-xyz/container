<?php

declare(strict_types=1);

namespace Projek\Container;

use Closure;
use Projek\Callable\Handler;
use Psr\Container\ContainerInterface;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionType;

/**
 * Abstract base owning one registration's identity, metadata, decorators and
 * singleton cache; the five concrete children under `Projek\Container\Entry\`
 * own the factory — the class is the kind.
 *
 * @internal
 *
 * @property mixed $factory
 */
abstract class Entry
{
    /**
     * Builtin type keywords: a stored return type naming one of these (or a
     * union/intersection) is never a named class type.
     */
    private const BUILTIN_TYPES = [
        'int',
        'float',
        'string',
        'bool',
        'array',
        'object',
        'mixed',
        'null',
        'void',
        'never',
        'iterable',
        'callable',
        'false',
        'true',
        'self',
        'static',
        'parent',
    ];

    /**
     * @var list<Closure> Appended only via decorate(); executed inside build().
     */
    private array $decorators = [];

    /**
     * Singleton cache, written by cache() only.
     */
    private bool $built = false;

    private mixed $value = null;

    /**
     * Re-entrancy guard, driven by beginBuild()/endBuild().
     */
    private bool $building = false;

    /**
     * @param  array<string, string|null>  $parameters  Parameter name => type (FQCN for class-typed named types, (string) $type for builtins/unions, null untyped).
     * @param  string|null  $returnType  Declared return type, self/static/parent resolved to FQCN at extraction.
     */
    public function __construct(
        public readonly string $id,
        public readonly array $parameters = [],
        public readonly ?string $returnType = null,
        public readonly bool $auto = false,
    ) {
        // .
    }

    /**
     * Reset the singleton cache and the guard; metadata, factory and
     * decorators copy by value (object values inside them are shared).
     */
    public function __clone()
    {
        $this->built = false;
        $this->value = null;
        $this->building = false;
    }

    /**
     * Show only these props from `var_dump()`
     */
    public function __debugInfo(): array
    {
        return [
            'decorators' => $this->decorators,
            'parameters' => $this->parameters,
            'returnType' => $this->returnType,
            'value' => $this->value,
        ];
    }

    /**
     * Adapt the child's factory shape and produce a raw value; never caches
     * and never dispatches events (get()/make() own that).
     *
     * @param  array<mixed>  $args
     */
    abstract protected function produce(Handler $handler, ContainerInterface $container, array $args): mixed;

    /**
     * The class type extend() derives from this entry, or null when not derivable.
     */
    abstract public function extensionTarget(EntryCollector $entries): ?string;

    /**
     * Build template: produce() yields the raw value, decorators always run
     * inside build() — a value returned from build() is fully decorated.
     *
     * @param  array<mixed>  $args
     */
    final public function build(Handler $handler, ContainerInterface $container, array $args = []): mixed
    {
        return $this->applyDecorators($handler, $this->produce($handler, $container, $args));
    }

    /**
     * Enter the build guard; callers run endBuild() in finally.
     *
     * @throws ResolutionException When this entry is already being built.
     */
    final public function beginBuild(): void
    {
        if ($this->building) {
            throw new ResolutionException(\sprintf('Failed to resolve "%s": circular reference while building.', $this->id));
        }

        $this->building = true;
    }

    /**
     * Leave the build guard — callers pair this with beginBuild() in a finally block.
     */
    final public function endBuild(): void
    {
        $this->building = false;
    }

    /**
     * Whether this entry itself is mid-build.
     */
    final public function building(): bool
    {
        return $this->building;
    }

    /**
     * Whether a value has been cached for this entry (written by cache() only).
     */
    final public function isBuilt(): bool
    {
        return $this->built;
    }

    /**
     * Run the whole decorator list from index 0 against the current value.
     */
    final public function applyDecorators(Handler $handler, mixed $value): mixed
    {
        foreach ($this->decorators as $decorator) {
            $value = $handler->handle($decorator, [$value]);
        }

        return $value;
    }

    /**
     * Append a decorator (called by extend()); never touches the cache.
     */
    final public function decorate(Closure $callback): void
    {
        $this->decorators[] = $callback;
    }

    /**
     * The cached value; null until cache() stores one — callers gate on isBuilt().
     */
    final public function value(): mixed
    {
        return $this->value;
    }

    /**
     * Cache an already-decorated value; non-object values cache like anything else.
     */
    final public function cache(mixed $value): void
    {
        $this->value = $value;
        $this->built = true;
    }

    /**
     * Shared metadata extraction over any reflected function/method.
     *
     * @return array{array<string, string|null>, string|null} [parameters, returnType]
     */
    final protected static function extractMetadata(ReflectionFunctionAbstract $reflection): array
    {
        $scope = null;

        if ($reflection instanceof ReflectionMethod) {
            $scope = $reflection->getDeclaringClass();
        } elseif ($reflection instanceof ReflectionFunction) {
            $scope = $reflection->getClosureScopeClass();
        }

        $resolve = static function (?ReflectionType $type) use ($scope): ?string {
            if ($type === null) {
                return null;
            }

            if (! $type instanceof ReflectionNamedType || $type->isBuiltin()) {
                return (string) $type;
            }

            $name = $type->getName();

            if ($name === 'self' || $name === 'static') {
                $name = $scope?->getName() ?? $name;
            } elseif ($name === 'parent' && ($parent = $scope?->getParentClass())) {
                $name = $parent->getName();
            }

            return $type->allowsNull() ? '?'.$name : $name;
        };

        $parameters = [];

        foreach ($reflection->getParameters() as $parameter) {
            $parameters[$parameter->getName()] = $resolve($parameter->getType());
        }

        return [$parameters, $resolve($reflection->getReturnType())];
    }

    /**
     * Interpret stored returnType metadata: the named class type an extend()
     * target can be derived from, or null (nothing declared, builtin,
     * union/intersection).
     */
    final protected static function namedClassType(?string $type): ?string
    {
        if ($type === null || \str_starts_with($type, '?') || \str_contains($type, '|') || \str_contains($type, '&')) {
            return null;
        }

        return \in_array($type, self::BUILTIN_TYPES, true) ? null : $type;
    }
}
