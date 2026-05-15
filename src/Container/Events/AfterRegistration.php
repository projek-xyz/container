<?php

declare(strict_types=1);

namespace Projek\Container\Events;

/**
 * @package Projek\Container
 * @codeCoverageIgnore
 */
final class AfterRegistration
{
    /**
     * @var callable|object $entry
     */
    private $entry;

    public function __construct(
        callable|object $entry,
        public string $id,
    ) {
        $this->entry = $entry;
    }

    public function getEntry(): callable|object
    {
        return $this->entry;
    }
}
