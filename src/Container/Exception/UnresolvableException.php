<?php

declare(strict_types=1);

namespace Projek\Container\Exception;

use Projek\Container\Exception;

class UnresolvableException extends Exception
{
    /**
     * Create instance.
     *
     * @param mixed $toResolve
     * @param \Throwable|null $prev
     */
    public function __construct($toResolve, ?\Throwable $prev = null)
    {
        if ($toResolve instanceof \Throwable && null === $prev) {
            $prev = $toResolve;
        }

        parent::__construct($this->getTypeString($toResolve), $prev);
    }

    /**
     * @param mixed $toResolve
     * @return string
     */
    private function getTypeString($toResolve): string
    {
        $message = 'Cannot resolve %s';

        if (is_string($toResolve)) {
            return sprintf($message, 'string: ' . $toResolve);
        }

        if (is_array($toResolve)) {
            if (! is_string($toResolve[0])) {
                $toResolve[0] = get_class($toResolve[0]);
            }

            return sprintf($message, 'array: [' . join(', ', $toResolve) . ']');
        }

        if ($toResolve instanceof NotFoundException) {
            return sprintf($message, 'container: ' . $toResolve->getName());
        }

        if ($toResolve instanceof \ReflectionException) {
            return sprintf($message, 'instance: ' . $toResolve->getMessage());
        }

        if ($toResolve instanceof \Throwable) {
            return $toResolve->getMessage();
        }

        return 'type: ' . (is_object($toResolve) ? get_class($toResolve) : gettype($toResolve));
    }
}
