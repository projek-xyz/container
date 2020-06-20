<?php

namespace Stubs;

class SomeClass implements CertainInterface
{
    public function handle(AbstractFoo $dummy): string
    {
        return $dummy->lorem();
    }

    public function shouldCalled($param = 'a value')
    {
        return $param;
    }
}
