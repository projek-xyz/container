<?php

declare(strict_types=1);

namespace Projek\Container\Events;

use Projek\Container\ContainerAware;
use Projek\Container\HasContainer;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\ListenerProviderInterface;

/**
 * Event listener provider for container events.
 *
 * This class provides listeners for container lifecycle events:
 * - BeforeRegistration: Before a service is registered.
 * - AfterRegistration: After a service is registered.
 * - BeforeResolution: Before a service is resolved.
 * - AfterResolution: After a service is resolved (handles ContainerAware injection).
 *
 * @package Projek\Container
 * @internal This class is for internal use by the Container.
 */
final class ListenerProvider implements ContainerAware, ListenerProviderInterface
{
    use HasContainer;

    /**
     * Get listeners for a specific event.
     *
     * @param object $event The event object.
     * @return iterable<callable> An iterable of listener callables.
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

    /**
     * Handle BeforeRegistration event.
     *
     * @param BeforeRegistration $event
     * @return BeforeRegistration
     */
    public function beforeRegistration(BeforeRegistration $event): BeforeRegistration
    {
        return $event;
    }

    /**
     * Handle AfterRegistration event.
     *
     * @param AfterRegistration $event
     * @return AfterRegistration
     */
    public function afterRegistration(AfterRegistration $event): AfterRegistration
    {
        return $event;
    }

    /**
     * Handle BeforeResolution event.
     *
     * @param BeforeResolution $event
     * @return BeforeResolution
     */
    public function beforeResolution(BeforeResolution $event): BeforeResolution
    {
        return $event;
    }

    /**
     * Handle AfterResolution event.
     *
     * Injects the container into ContainerAware instances after resolution.
     *
     * @param AfterResolution $event
     * @return AfterResolution
     */
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
