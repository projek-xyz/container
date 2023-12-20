<?php

namespace Stubs;

class Dummy
{
    public function lorem(AbstractFoo $foo, $text = 'dummy lorem')
    {
        return $foo->lorem($text);
    }

    public function nonStaticMethod(?string $param = null)
    {
        return $param ?: 'value from non-static method';
    }

    public static function staticMethod(?string $param = null)
    {
        return $param ?: 'value from static method';
    }
}

function dummyLorem(AbstractFoo $foo) {
    return $foo->lorem();
}
