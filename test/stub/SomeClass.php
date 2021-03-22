<?php

namespace Stubs;

class SomeClass implements CertainInterface
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

    public function nonStaticMethod(?string $param = null)
    {
        return $param ?: 'value from non-static method';
    }

    public static function staticMethod(?string $param = null)
    {
        return $param ?: 'value from static method';
    }

    public function voidMethod()
    {
        //
    }
}
