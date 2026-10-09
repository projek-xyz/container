<?php

declare(strict_types=1);

namespace Stubs;

class ServiceProvider
{
    protected AbstractFoo $abs;

    public function __construct(AbstractFoo $abs)
    {
        $this->abs = $abs;
    }

    /**
     * @param  Dummy  $dummy
     * @return string
     */
    public function __invoke($dummy)
    {
        return $dummy->lorem($this->abs);
    }
}
