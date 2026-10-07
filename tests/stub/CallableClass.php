<?php

declare(strict_types=1);

namespace Stubs;

class CallableClass
{
    use RequireDummy;

    public function __invoke(AbstractFoo $foo)
    {
        return $foo;
    }
}
