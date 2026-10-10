<?php

declare(strict_types=1);

namespace Projek\Container\Events;

use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\EventDispatcher\ListenerProviderInterface;
use Psr\EventDispatcher\StoppableEventInterface;

/**
 * Internal minimalist PSR-14 Event Dispatcher.
 *
 * Fallback used when no dispatcher is passed to the container: it walks the
 * `ListenerProviderInterface` in order and honors `StoppableEventInterface`.
 *
 * @internal
 */
final class Dispatcher implements EventDispatcherInterface
{
    /**
     * @var ListenerProviderInterface
     */
    private $provider;

    public function __construct(
        ContainerInterface $container,
        ?ListenerProviderInterface $provider = null
    ) {
        $this->provider = $provider ?? (new ListenerProvider)->setContainer($container);
    }

    /**
     * {@inheritdoc}
     */
    public function dispatch(object $event): object
    {
        foreach ($this->provider->getListenersForEvent($event) as $listener) {
            if ($event instanceof StoppableEventInterface && $event->isPropagationStopped()) {
                break;
            }

            $listener($event);
        }

        return $event;
    }
}
