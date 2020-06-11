<?php

namespace Projek\ContainerStub;

class Dummy
{
    public function lorem(AbstractFoo $foo)
    {
        return $foo->lorem();
    }
}
