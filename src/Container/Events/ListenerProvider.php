<?php

declare(strict_types=1);

namespace Projek\Container\Events;

use Projek\Container\ContainerAware;
use Projek\Container\HasContainer;
use Psr\EventDispatcher\ListenerProviderInterface;

/**
 * Event listener provider for container events.
 *
 * Maps only EntryResolved to the container-aware injection listener;
 * EntryRegistered has no internal listener — it is a user-facing notification.
 *
 * @internal This class is for internal use by the Container.
 */
final class ListenerProvider implements ContainerAware, ListenerProviderInterface
{
    use HasContainer;

    /**
     * Get listeners for a specific event.
     *
     * @param  object  $event  The event object.
     * @return iterable<callable> An iterable of listener callables.
     */
    public function getListenersForEvent(object $event): iterable
    {
        $listeners = [
            EntryResolved::class => ['entryResolved'],
        ];

        return \array_map(
            fn ($listener) => [$this, $listener],
            $listeners[\get_class($event)] ?? [],
        );
    }

    /**
     * Handle EntryResolved event.
     *
     * Injects the container into a ContainerAware instance resolved through a
     * fresh get() build (no id guard needed: self/ContainerInterface entries
     * are auto and never dispatch).
     */
    public function entryResolved(EntryResolved $event): EntryResolved
    {
        if ($event->instance instanceof ContainerAware && $event->instance->getContainer() === null) {
            $event->instance->setContainer($this->getContainer());
        }

        return $event;
    }
}
