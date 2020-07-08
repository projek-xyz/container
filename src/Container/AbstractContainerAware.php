<?php

declare(strict_types=1);

namespace Projek\Container;

abstract class AbstractContainerAware implements ContainerAwareInterface
{
    use ContainerAware;

    /**
     * Create new instance.
     *
     * @param ContainerInterface $container
     */
    final public function __construct(ContainerInterface $container)
    {
        $this->setContainer($container);
    }
}
