<?php

declare(strict_types=1);

namespace Projek\Container\Events;

use Projek\Container\ContainerAware;
use Projek\Container\HasContainer;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\ListenerProviderInterface;

/**
 * @package Projek\Container
 * @internal
 */
final class ListenerProvider implements ContainerAware, ListenerProviderInterface
{
    use HasContainer;

    /**
     * @param object $event
     * @return iterable<callable>
     */
    public function getListenersForEvent(object $event): iterable
    {
        $listeners = [
            BeforeRegistration::class => ['beforeRegistration'],
            AfterRegistration::class => ['afterRegistration'],
            BeforeResolution::class => ['beforeResolution'],
            AfterResolution::class => ['afterResolution'],
        ];

        return array_map(
            fn ($listener) => [$this, $listener],
            $listeners[get_class($event)] ?? [],
        );
    }

    public function beforeRegistration(BeforeRegistration $event): BeforeRegistration
    {
        return $event;
    }

    public function afterRegistration(AfterRegistration $event): AfterRegistration
    {
        return $event;
    }

    public function beforeResolution(BeforeResolution $event): BeforeResolution
    {
        return $event;
    }

    public function afterResolution(AfterResolution $event): AfterResolution
    {
        $entry = $event->getEntry();

        if (
            $entry instanceof ContainerAware &&
            $event->id !== ContainerInterface::class &&
            null === $entry->getContainer()
        ) {
            $entry->setContainer($this->getContainer());
        }

        return $event;
    }
}
