<?php

declare(strict_types=1);

namespace Projek\Container\Exception;

use Projek\Container\Exception;

class UnresolvableException extends Exception
{
    public function __construct($toResolve, ?\Throwable $prev = null)
    {
        parent::__construct(sprintf('Couldn\'t resolve %s.', $this->getTypeString($toResolve)), $prev);
    }

    private function getTypeString($toResolve)
    {
        if (is_string($toResolve)) {
            return 'string: ' . $toResolve;
        } elseif (is_array($toResolve)) {
            if (! is_string($toResolve[0])) {
                $toResolve[0] = get_class($toResolve[0]);
            }

            return 'array: [' . join(', ', $toResolve) . ']';
        }

        return 'type: ' . gettype($toResolve);
    }
}
