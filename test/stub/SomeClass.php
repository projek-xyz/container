<?php

namespace Stubs;

class SomeClass implements CertainInterface
{
    public function __invoke($param = null)
    {
        return $param ?? $this;
    }

    public function handle(AbstractFoo $dummy): string
    {
        return $dummy->lorem();
    }

    public function shouldCalled($param = 'a value')
    {
        return $param;
    }

    public static function staticMethod($param = 'a value')
    {
        return $param;
    }

    public function voidMethod()
    {
        //
    }
}
