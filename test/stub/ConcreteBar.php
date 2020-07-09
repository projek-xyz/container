<?php

namespace Stubs;

class ConcreteBar extends AbstractFoo
{
    use RequireDummy;

    public static function std($std)
    {
        return $std;
    }
}
