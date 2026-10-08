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
}
