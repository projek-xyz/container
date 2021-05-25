<?php

namespace Stubs;

abstract class AbstractFoo
{
    public function lorem(?string $text = null)
    {
        return $text ?: 'lorem';
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
