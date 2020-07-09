<?php

namespace Stubs;

trait RequireDummy
{
    /**
     * @var Dummy
     */
    public $dummy;

    public function __construct($dummy)
    {
        $this->dummy = $dummy;
    }
}
