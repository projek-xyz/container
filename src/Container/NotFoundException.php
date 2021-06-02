<?php

declare(strict_types=1);

namespace Projek\Container;

use Psr\Container\NotFoundExceptionInterface;

/**
 * @package Projek\Container
 */
class NotFoundException extends \InvalidArgumentException implements NotFoundExceptionInterface
{
    /**
     * @var string
     */
    private $name;

    /**
     * @param string $name
     * @param \Throwable|null $prev
     */
    public function __construct(string $name, ?\Throwable $prev = null)
    {
        $this->name = $name;
        parent::__construct(\sprintf('Container entry "%s" not found.', $name), 0, $prev);
    }

    /**
     * Retrieve entry name.
     *
     * @return string
     */
    final public function getName(): string
    {
        return $this->name;
    }
}
