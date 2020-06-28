<?php

declare(strict_types=1);

namespace Projek\Container;

interface ContainerAwareInterface
{
    /**
     * Assign a container to the instance.
     *
     * @param ContainerInterface $container
     * @return void
     */
    public function setContainer(ContainerInterface $container): void;

    /**
     * Get container instance or the instance of registered service.
     *
     * @param null|string $name Optionally pass a container name, if needed.
     * @return null|mixed|ContainerInterface
     */
    public function getContainer(?string $name = null);
}
