<?php

namespace Stubs;

class Dummy
{
    public function lorem(AbstractFoo $foo)
    {
        return $foo->lorem();
    }
}

function dummyLorem(AbstractFoo $foo) {
    return $foo->lorem();
}
