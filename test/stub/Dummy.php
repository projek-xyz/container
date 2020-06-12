<?php

namespace Stubs;

class Dummy
{
    public function lorem(AbstractFoo $foo, $text = 'dummy lorem')
    {
        return $foo->lorem($text);
    }
}

function dummyLorem(AbstractFoo $foo) {
    return $foo->lorem();
}
