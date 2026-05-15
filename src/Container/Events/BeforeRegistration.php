<?php

declare(strict_types=1);

namespace Projek\Container\Events;

/**
 * @package Projek\Container
 * @codeCoverageIgnore
 */
final class BeforeRegistration
{
    /**
     * @var array{class-string<object>|string,string}|callable|string $factory
     */
    private $factory;

    /**
     * @param array{class-string<object>|string,string}|callable|string $factory
     */
    public function __construct(
        array|callable|string $factory,
        public string $id,
    ) {
        $this->factory = $factory;
    }

    /**
     * @param array{class-string<object>|string,string}|callable|string $factory
     */
    public function setFactory(array|callable|string $factory): void
    {
        $this->factory = $factory;
    }

    /**
     * @return array{class-string<object>|string,string}|callable|string
     */
    public function getFactory(): array|callable|string
    {
        return $this->factory;
    }
}
