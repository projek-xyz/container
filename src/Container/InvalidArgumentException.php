<?php

declare(strict_types=1);

namespace Projek\Container;

/**
 * Exception thrown when an invalid argument is provided during service resolution.
 */
class InvalidArgumentException extends \InvalidArgumentException
{
    /**
     * By-reference parameter rejection, shared byte-identically by
     * CallableEntry (§5 row 1) and ClassNameEntry (§5 row 3b).
     */
    public static function byReferenceParam(string $id, string $param): static
    {
        return new static(\sprintf(
            'Cannot register entry "%s": by-reference parameter $%s is not allowed.',
            $id,
            $param
        ));
    }

    /**
     * Duplicate registration of a non-auto entry (set()).
     */
    public static function alreadyRegistered(string $id): static
    {
        return new static(\sprintf(
            'Cannot register entry "%s": already registered.',
            $id
        ));
    }

    /**
     * A string factory naming no registered entry, instantiable class, nor function (set()).
     */
    public static function unresolvableString(string $id, string $factory): static
    {
        return new static(\sprintf(
            'Cannot register entry "%s": "%s" is neither a registered entry, an instantiable class, nor a function.',
            $id,
            $factory,
        ));
    }

    /**
     * A plain object passed as a factory (shared by set() and CallableEntry).
     */
    public static function plainObjectNotAFactory(string $id, mixed $factory): static
    {
        return new static(\sprintf(
            'Cannot register entry "%s": plain object %s is not a factory — register instances as "fn () => $instance" or "new EntryFactory(...)"',
            $id,
            \get_debug_type($factory),
        ));
    }

    /**
     * A factory of an unsupported type in set()'s default dispatch row.
     */
    public static function invalidFactoryType(string $id, mixed $factory): static
    {
        return new static(\sprintf(
            'Cannot register entry "%s": invalid factory of type %s',
            $id,
            \get_debug_type($factory),
        ));
    }

    /**
     * A make() target that is a plain object without __invoke.
     */
    public static function cannotMakePlainObject(string $target): static
    {
        return new static(\sprintf(
            'Cannot make from "%s": %s',
            $target,
            'plain object has no __invoke — make() accepts a registered entry id, an instantiable class-string, or a callable; pass "fn () => …" instead.',
        ));
    }

    /**
     * A make() target outside all accepted families.
     */
    public static function cannotMakeUnsupported(string $target): static
    {
        return new static(\sprintf(
            'Cannot make from "%s": %s',
            $target,
            'make() accepts a registered entry id, an instantiable class-string, or a callable.',
        ));
    }

    /**
     * An extend() target whose type is not derivable from the entry.
     */
    public static function extensionTargetNotDerivable(string $id): static
    {
        return new static(\sprintf(
            'Cannot extend entry "%s": extension target type is not derivable.',
            $id
        ));
    }

    /**
     * An extend() callback without an explicit, non-union, named return type.
     */
    public static function callbackReturnTypeInvalid(string $id): static
    {
        return new static(\sprintf(
            'Cannot extend entry "%s": callback must declare an explicit, non-union, named return type.',
            $id
        ));
    }

    /**
     * An extend() callback whose declared return type mismatches the target.
     */
    public static function callbackReturnMismatch(string $id, string $target): static
    {
        return new static(\sprintf(
            'Cannot extend entry "%s": callback must return "%s"',
            $id,
            $target
        ));
    }

    /**
     * An attempt to remove an entry through EntryCollector::offsetUnset().
     */
    public static function removalNotSupported(mixed $id): static
    {
        return new static(\sprintf(
            'Removing registered entry "%s" is not supported.',
            (string) $id
        ));
    }

    /**
     * A string factory without a "Class::method" separator (MethodPairEntry).
     */
    public static function notClassMethodString(string $id, string $factory): static
    {
        return new static(\sprintf(
            'Cannot register entry "%s": "%s" is not a "Class::method" string.',
            $id,
            $factory
        ));
    }

    /**
     * A method pair that is not exactly [class, method] (MethodPairEntry).
     */
    public static function invalidPairSize(string $id): static
    {
        return new static(\sprintf(
            'Cannot register entry "%s": method pair must contain exactly two elements [class, method].',
            $id
        ));
    }

    /**
     * A pair whose class does not exist (MethodPairEntry).
     */
    public static function pairClassNotFound(string $id, mixed $class): static
    {
        return new static(\sprintf(
            'Cannot register entry "%s": class "%s" does not exist.',
            $id,
            \is_string($class) ? $class : \get_debug_type($class)
        ));
    }

    /**
     * A pair whose method does not exist on the class (MethodPairEntry).
     */
    public static function pairMethodNotFound(string $id, string $class, mixed $method): static
    {
        return new static(\sprintf(
            'Cannot register entry "%s": method "%s::%s()" does not exist.',
            $id,
            $class,
            \is_string($method) ? $method : \get_debug_type($method)
        ));
    }

    /**
     * A pair naming a non-public method (MethodPairEntry).
     */
    public static function pairMethodNotPublic(string $id, string $class, string $method): static
    {
        return new static(\sprintf(
            'Cannot register entry "%s": method "%s::%s()" is not public.',
            $id,
            $class,
            $method
        ));
    }

    /**
     * A class-string that is not an instantiable class (ClassNameEntry).
     */
    public static function notInstantiable(string $id, string $factory): static
    {
        return new static(\sprintf(
            'Cannot register entry "%s": "%s" is not an instantiable class.',
            $id,
            $factory
        ));
    }

    /**
     * A string factory that is not a function (CallableEntry).
     */
    public static function notAFunction(string $id, string $factory): static
    {
        return new static(\sprintf(
            'Cannot register entry "%s": "%s" is not a function.',
            $id,
            $factory
        ));
    }
}
