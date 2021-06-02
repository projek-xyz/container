<?php

declare(strict_types=1);

namespace Projek\Container;

/**
 * This class is internal uses, please implements `ContainerAware` and use
 * `HasContainer` trait yourself instead.
 *
 * @package Projek\Container
 * @internal
 */
abstract class AbstractContainerAware implements ContainerAware
{
    use HasContainer;
}
