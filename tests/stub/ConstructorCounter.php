<?php

declare(strict_types=1);

namespace Stubs;

class ConstructorCounter
{
    public static int $count = 0;

    public function __construct()
    {
        self::$count++;
    }
}
