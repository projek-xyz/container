<?php

declare(strict_types=1);

namespace Stubs;

/**
 * Recording dispatcher: forwards every event to its ListenerProvider (so the
 * container's injection listener still runs) while capturing the stream for
 * event assertions.
 */
class RecordingDispatcher extends TheDispatcher
{
    /**
     * @var list<object> Every event dispatched through this instance, in order.
     */
    public array $events = [];

    /**
     * Record the event, then forward it to the parent's provider walk.
     */
    public function dispatch(object $event): object
    {
        $this->events[] = $event;

        return parent::dispatch($event);
    }

    /**
     * @param  class-string  $class
     * @return list<object>
     */
    public function eventsFor(string $class): array
    {
        return \array_values(\array_filter($this->events, fn (object $event) => $event instanceof $class));
    }

    /**
     * Clear the recorded event stream.
     */
    public function reset(): void
    {
        $this->events = [];
    }
}
