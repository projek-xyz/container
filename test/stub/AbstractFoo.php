<?php

namespace Stubs;

abstract class AbstractFoo
{
    public function lorem(?string $text = null)
    {
        return $text ?: 'lorem';
    }
}
