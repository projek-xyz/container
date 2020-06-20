<?php

namespace Stubs;

class CallableClass
{
    /**
     * @var stdClass
     */
    public $dummy;

    public function __construct($dummy)
    {
        $this->dummy = $dummy;
    }

    public function __invoke(AbstractFoo $foo)
    {
        return $foo;
    }
}
