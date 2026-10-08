<?php

declare(strict_types=1);

namespace Stubs;

/**
 * Required multi-parameter constructor for the ctor-binding matrix
 * (positional-by-order, named, resolveParameter() fallback).
 */
class MultiParamStub
{
    public function __construct(
        public AbstractFoo $foo,
        public string $name,
    ) {
        // .
    }

    /**
     * Named-class (`self`) return type for MethodPairEntry::extensionTarget()
     * and for the metadata extraction's self-to-FQCN resolution.
     */
    public function chain(self $next): self
    {
        return $next;
    }

    /**
     * Non-public method for MethodPairEntry's public-method validation.
     */
    private function hidden(): void
    {
        // .
    }
}
