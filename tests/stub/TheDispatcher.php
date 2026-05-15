<?php

declare(strict_types=1);

namespace Stubs;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\EventDispatcher\ListenerProviderInterface;

class TheDispatcher implements EventDispatcherInterface
{
    public function __construct(
        private ListenerProviderInterface $listenerProvider
    ) {
    }

    public function dispatch(object $event): object
    {
        foreach ($this->listenerProvider->getListenersForEvent($event) as $listener) {
            $event = $listener($event);
        }

        return $event;
    }
}
