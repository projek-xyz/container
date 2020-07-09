<?php

namespace Stubs;

class ServiceProvider
{
    protected $abs;

    public function __construct(AbstractFoo $abs)
    {
        $this->abs = $abs;
    }

    /**
     * @param Dummy $d
     * @return string
     */
    public function __invoke($dummy)
    {
        return $dummy->lorem($this->abs);
    }

    public function nonStaticMethod()
    {
        return 'value from non-static method';
    }

    public static function staticMethod()
    {
        return 'value from static method';
    }
}
