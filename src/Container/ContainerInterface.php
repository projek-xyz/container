<?php

declare(strict_types=1);

namespace Projek\Container;

use Psr\Container\ContainerInterface as PsrContainerInterface;

interface ContainerInterface extends PsrContainerInterface
{
    /**
     * Add new instance.
     *
     * @param string $id
     * @param mixed $instance
     * @return void
     */
    public function set(string $id, $instance): void;

    /**
     * Unset instance.
     *
     * @param string $id
     * @return void
     */
    public function unset(string $id): void;
}
