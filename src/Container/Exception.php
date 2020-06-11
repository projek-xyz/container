<?php

namespace Projek\Container;

use Psr\Container\ContainerExceptionInterface;

class Exception extends \RuntimeException implements ContainerExceptionInterface
{
    final static function unresolvable($abstract)
    {
        if (! is_string($abstract)) {
            $abstract = gettype($abstract);
        }

        return new static(
            sprintf('Couldn\'t resolve "%s" as an instance.', $abstract)
        );
    }

    final static function notInstantiable(string $class)
    {
        return new static(
            sprintf('Target "%s" is not instantiable.', $class)
        );
    }
}
