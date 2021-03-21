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

        parent::__construct(sprintf('Couldn\'t resolve %s.', $this->getTypeString($toResolve)), $prev);
    }

    /**
     * @param mixed $toResolve
     * @return string
     */
    private function getTypeString($toResolve): string
    {
        if (is_string($toResolve)) {
            return 'string: ' . $toResolve;
        }

        if (is_array($toResolve)) {
            if (! is_string($toResolve[0])) {
                $toResolve[0] = get_class($toResolve[0]);
            }

            return 'array: [' . join(', ', $toResolve) . ']';
        }

        if ($toResolve instanceof NotFoundException) {
            return 'container: ' . $toResolve->getName();
        }

        return 'type: ' . gettype($toResolve);
    }
}
