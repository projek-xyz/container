<?php

declare(strict_types=1);

namespace Stubs;

class ConcreteBar extends AbstractFoo
{
    use RequireDummy;

    public static function std($std)
    {
        return $std;
    }
}
