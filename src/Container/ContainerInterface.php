<?php

namespace Projek\Container;

use Psr\Container\ContainerInterface as PsrContainerInterface;

interface ContainerInterface extends PsrContainerInterface
{
    /**
     * Add new instance.
     *
     * @param string $abstract
     * @param mixed $concrete
     * @return void
     */
    public function set(string $abstract, $concrete) : void;

    /**
     * Unset instance.
     *
     * @param string $abstract
     * @return void
     */
    public function unset(string $abstract) : void;
}
