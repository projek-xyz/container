<?php

namespace Stubs;

class SomeClass extends Dummy implements CertainInterface
{
    public function __invoke($param = null)
    {
        return $param ?? $this;
    }

    public function handle(AbstractFoo $dummy, ?string $text = null): string
    {
        return $dummy->lorem($text);
    }

    public function shouldCalled($param = 'a value')
    {
        return $param;
    }

    public function voidMethod()
    {
        //
    }
}
