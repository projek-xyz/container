<?php

declare(strict_types=1);

namespace Projek\Container;

use Psr\Container\ContainerExceptionInterface;

class Exception extends \RuntimeException implements ContainerExceptionInterface
{
    /**
     * When there's no way to resolve $abstract.
     *
     * @param mixed $abstract
     * @param \Throwable $pref
     * @return static
     */
    final public static function unresolvable($abstract, \Throwable $prev = null)
    {
        if (! is_string($abstract)) {
            $abstract = gettype($abstract);
        }

        return new static(sprintf('Couldn\'t resolve "%s" as an instance.', $abstract), 0, $prev);
    }

    /**
     * When the $class is not instantiable.
     *
     * @param class $class
     * @param \Throwable $pref
     * @return static
     */
    final public static function notInstantiable(string $class, \Throwable $prev = null)
    {
        return new static(sprintf('Target "%s" is not instantiable.', $class), 0, $prev);
    }
}
