<?php

declare(strict_types=1);

namespace Stubs;

class BuiltinParamStub
{
    /**
     * A required builtin-typed parameter: never auto-wirable, so unprovided construction fails.
     */
    public function __construct(
        public int $count,
    ) {
        // .
    }
}
