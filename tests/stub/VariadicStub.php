<?php

declare(strict_types=1);

namespace Stubs;

/**
 * Trailing-variadic constructor for the ctor-binding matrix (leftover
 * arguments are spliced into the variadic exactly like a native call).
 */
class VariadicStub
{
    /** @var array<mixed> */
    public array $extras;

    public function __construct(public AbstractFoo $foo, ...$extras)
    {
        $this->extras = $extras;
    }
}
