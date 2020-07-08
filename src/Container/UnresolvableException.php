<?php

declare(strict_types=1);

namespace Projek\Container;

class UnresolvableException extends Exception
{
    public function __construct($toResolve, ?\Throwable $prev = null)
    {
        if (is_string($toResolve)) {
            $toResolve = 'string: ' . $toResolve;
        } elseif (is_array($toResolve)) {
            $toResolve = 'array: [' . join(', ', $toResolve) . ']';
        } else {
            $toResolve = 'type: ' . gettype($toResolve);
        }

        parent::__construct(sprintf('Couldn\'t resolve %s.', $toResolve), 0, $prev);
    }
}
