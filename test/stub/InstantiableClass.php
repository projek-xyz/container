<?php

namespace Stubs;

class InstantiableClass
{
    /**
     * @var stdClass
     */
    public $dummy;

    public function __construct($dummy)
    {
        $this->dummy = $dummy;
    }
}
