<?php

declare(strict_types=1);

namespace Projek\Container;

/**
 * Exception thrown when the container rejects a factory, target or callback handed to `set()`,
 * `make()` or `extend()` — a duplicate id, an unsupported factory shape, an unresolvable string,
 * a non-derivable extension target or a bad callback return type. These are always programming
 * errors, never lookup misses (`NotFoundException`) or build failures (`ResolutionException`).
 *
 * Extends the SPL `\InvalidArgumentException` and, like it, carries no PSR container marker.
 */
final class InvalidArgumentException extends \InvalidArgumentException
{
    /**
     * By-reference parameter rejection, shared byte-identically by `CallableEntry` and
     * `ClassNameEntry`.
     *
     * @internal
     */
    public static function byReferenceParam(string $id, string $param): static
    {
        return new self(\sprintf(
            'Cannot register entry "%s": by-reference parameter $%s is not allowed.',
            $id,
            $param
        ));
    }

    /**
     * A duplicate `set()` registration of a non-`auto` entry.
     *
     * @internal
     */
    public static function alreadyRegistered(string $id): static
    {
        return new self(\sprintf(
            'Cannot register entry "%s": already registered.',
            $id
        ));
    }

    /**
     * A string factory naming no registered entry, instantiable class, nor function (`set()`).
     *
     * @internal
     */
    public static function unresolvableString(string $id, string $factory): static
    {
        return new self(\sprintf(
            'Cannot register entry "%s": "%s" is neither a registered entry, an instantiable class, nor a function.',
            $id,
            $factory,
        ));
    }

    /**
     * A plain object passed as a factory (shared by `set()` and `CallableEntry`).
     *
     * @internal
     */
    public static function plainObjectNotAFactory(string $id, mixed $factory): static
    {
        return new self(\sprintf(
            'Cannot register entry "%s": plain object %s is not a factory — register instances as "fn () => $instance" or "new EntryFactory(...)"',
            $id,
            \get_debug_type($factory),
        ));
    }

    /**
     * A factory of an unsupported type: the catch-all arm of `set()`'s dispatch rejects it.
     *
     * @internal
     */
    public static function invalidFactoryType(string $id, mixed $factory): static
    {
        return new self(\sprintf(
            'Cannot register entry "%s": invalid factory of type %s',
            $id,
            \get_debug_type($factory),
        ));
    }

    /**
     * A `make()` target that is a plain object without `__invoke()`.
     *
     * @internal
     */
    public static function cannotMakePlainObject(string $target): static
    {
        return new self(\sprintf(
            'Cannot make from "%s": %s',
            $target,
            'plain object has no __invoke — make() accepts a registered entry id, an instantiable class-string, or a callable; pass "fn () => …" instead.',
        ));
    }

    /**
     * A `make()` target outside all accepted families.
     *
     * @internal
     */
    public static function cannotMakeUnsupported(string $target): static
    {
        return new self(\sprintf(
            'Cannot make from "%s": %s',
            $target,
            'make() accepts a registered entry id, an instantiable class-string, or a callable.',
        ));
    }

    /**
     * An `extend()` target whose type is not derivable from the entry.
     *
     * @internal
     */
    public static function extensionTargetNotDerivable(string $id): static
    {
        return new self(\sprintf(
            'Cannot extend entry "%s": extension target type is not derivable.',
            $id
        ));
    }

    /**
     * An `extend()` callback without an explicit, non-union, named return type.
     *
     * @internal
     */
    public static function callbackReturnTypeInvalid(string $id): static
    {
        return new self(\sprintf(
            'Cannot extend entry "%s": callback must declare an explicit, non-union, named return type.',
            $id
        ));
    }

    /**
     * An `extend()` callback whose declared return type mismatches the target.
     *
     * @internal
     */
    public static function callbackReturnMismatch(string $id, string $target): static
    {
        return new self(\sprintf(
            'Cannot extend entry "%s": callback must return "%s"',
            $id,
            $target
        ));
    }

    /**
     * An attempt to remove an entry through `EntryCollector::offsetUnset()`.
     *
     * @internal
     */
    public static function removalNotSupported(mixed $id): static
    {
        return new self(\sprintf(
            'Removing registered entry "%s" is not supported.',
            (string) $id
        ));
    }

    /**
     * A malformed `Class::method` string or `[class, method]` pair factory.
     *
     * @internal
     *
     * @param  string|array{mixed,mixed}  $factory
     */
    public static function invalidMethodPair(string $id, string|array $factory): static
    {
        if (is_array($factory)) {
            $class = var_export($factory[0] ?? null, true);
            $method = var_export($factory[1] ?? null, true);
            $factory = "[$class, $method]";
        }

        return new self(\sprintf(
            'Cannot register entry "%s": method pair must contain exactly two elements [class, method] or Class::method, "%s" given.',
            $id,
            $factory
        ));
    }

    /**
     * A `MethodPairEntry` pair whose class does not exist.
     *
     * @internal
     */
    public static function pairClassNotFound(string $id, mixed $class): static
    {
        return new self(\sprintf(
            'Cannot register entry "%s": class "%s" does not exist.',
            $id,
            \is_string($class) ? $class : \get_debug_type($class)
        ));
    }

    /**
     * A `MethodPairEntry` pair whose method does not exist on the class.
     *
     * @internal
     */
    public static function pairMethodNotFound(string $id, string $class, mixed $method): static
    {
        return new self(\sprintf(
            'Cannot register entry "%s": method "%s::%s()" does not exist.',
            $id,
            $class,
            \is_string($method) ? $method : \get_debug_type($method)
        ));
    }

    /**
     * A `MethodPairEntry` pair naming a non-public method.
     *
     * @internal
     */
    public static function pairMethodNotPublic(string $id, string $class, string $method): static
    {
        return new self(\sprintf(
            'Cannot register entry "%s": method "%s::%s()" is not public.',
            $id,
            $class,
            $method
        ));
    }

    /**
     * A class-string that is not an instantiable class (`ClassNameEntry`).
     *
     * @internal
     */
    public static function notInstantiable(string $id, string $factory): static
    {
        return new self(\sprintf(
            'Cannot register entry "%s": "%s" is not an instantiable class.',
            $id,
            $factory
        ));
    }

    /**
     * A string factory that is not a function (`CallableEntry`).
     *
     * @internal
     */
    public static function notAFunction(string $id, string $factory): static
    {
        return new self(\sprintf(
            'Cannot register entry "%s": "%s" is not a function.',
            $id,
            $factory
        ));
    }
}
