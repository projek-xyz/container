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
        if ($toResolve instanceof self) {
            return $toResolve->getMessage();
        }

        switch (true) {
            case is_array($toResolve):
                if (! is_string($toResolve[0])) {
                    $toResolve[0] = get_class($toResolve[0]);
                }

                $message = 'array: [' . join(', ', $toResolve) . ']';
                break;
            case $toResolve instanceof NotFoundException:
                $message = 'container: ' . $toResolve->getName();
                break;
            case $toResolve instanceof \ReflectionException:
                $message = 'instance: ' . str_replace('"', '', $toResolve->getMessage());
                break;
            case is_object($toResolve):
                $message = 'class: ' . get_class($toResolve);
                break;
            default:
                $message = 'type: ' . gettype($toResolve);
                break;
        }

        return sprintf('Cannot resolve %s', $message);
    }
}
