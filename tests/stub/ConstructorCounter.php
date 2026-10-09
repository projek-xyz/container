<?php

declare(strict_types=1);

namespace Stubs;

class ConstructorCounter
{
    public static int $count = 0;

    /**
     * Bump the construction counter — specs count builds through the static $count.
     */
    public function __construct()
    {
        self::$count++;
    }
}
