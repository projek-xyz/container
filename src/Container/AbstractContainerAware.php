<?php

declare(strict_types=1);

namespace Projek\Container;

/**
 * Base class for services that require container awareness.
 *
 * This class provides a default implementation of the ContainerAware interface
 * via the HasContainer trait.
 *
 * @package Projek\Container
 * @internal This class is for internal use. Please implement `ContainerAware`
 *           and use the `HasContainer` trait in your own classes instead.
 */
abstract class AbstractContainerAware implements ContainerAware
{
    use HasContainer;
}
