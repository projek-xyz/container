<?php

declare(strict_types=1);

namespace Stubs;

/**
 * Optional/defaulted constructor parameters for the ctor-binding matrix
 * (unprovided parameters fall back to their default value).
 */
class DefaultParamsStub
{
    public function __construct(
        public string $name = 'default',
        public ?AbstractFoo $foo = null,
    ) {
        // .
    }
}
