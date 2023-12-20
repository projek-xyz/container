<?php

namespace Stubs;

class CallableClass
{
    use RequireDummy;

    public function __invoke(AbstractFoo $foo)
    {
        return $foo;
    }
}
