<?php

declare(strict_types=1);

namespace Projek\Container;

/**
 * This class is internal uses, please impleents `ContainerAwareInterface` and
 * use `ContainerAware` trait yourself instead.
 *
 * @internal
 */
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
