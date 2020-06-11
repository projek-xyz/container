<?php

namespace Projek\ContainerStub;

class ConcreteBar extends AbstractFoo
{
    /**
     * @var stdClass
     */
    public $dummy;

    public function __construct($dummy)
    {
        $this->dummy = $dummy;
    }

    public static function std($std)
    {
        return $std;
    }
}
