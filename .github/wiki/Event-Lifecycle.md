# Event Lifecycle

The Projek Container provides an event lifecycle based on the **PSR-14 Event Dispatcher** standard. Events are **notifications**: they let you observe registrations and fresh resolutions at key points of the container's lifecycle.

As of version 2.0 there are exactly **two events**, and both carry **readonly** payloads — listeners observe, they can no longer mutate anything. The old `BeforeRegistration` / `AfterRegistration` / `BeforeResolution` / `AfterResolution` events (which could swap factories, redirect ids, and replace resolved entries) are gone.

## Architecture

This library provides the **Event Classes** and a **`ListenerProvider`** for internal features like `ContainerAware` injection. 

While the container includes a minimalist internal `EventDispatcher` to ensure core features work out-of-the-box, it is designed to be used with a full-featured PSR-14 implementation.

The developer can:
1.  **Use the default**: No configuration needed; `ContainerAware` injection works automatically.
2.  **Provide a custom dispatcher**: Pass a PSR-14 compliant `EventDispatcherInterface` (e.g., `symfony/event-dispatcher`) to the constructor or via `setEventDispatcher()`.

> [!NOTE]
> A custom dispatcher only runs the container's injection listener if you wire the container's `ListenerProvider` into it — see [Container Awareness](Container-Awareness).

## Usage

```php
use Projek\Container;
use Your\Psr14\Dispatcher;

$dispatcher = new Dispatcher();
$container = new Container($entries, $dispatcher);

// OR assign later:
$container->setEventDispatcher($dispatcher);
```

## Available Events

| Event | Fired from | Payload |
| --- | --- | --- |
| `EntryRegistered` | The end of every `Container::set()` (including when it replaces an infrastructure default) and `Container::setEventDispatcher()` | `public readonly Entry $entry` |
| `EntryResolved` | A **fresh** `Container::get()` build — **after** the instance has been written to the cache | `public readonly string $id`, `public readonly object $instance` |

### `EntryRegistered`

Dispatched once a user registration is complete. The payload is the internal `Entry` — id, raw factory, parameter metadata, return type, and the `auto` flag — so listeners can inspect (not modify) what was just registered.

```php
use Projek\Container\Events\EntryRegistered;

// In a listener:
public function onEntryRegistered(EntryRegistered $event): void
{
    $entry = $event->entry;

    if (! $entry->auto) {
        // e.g. collect ids for a service manifest
        $this->manifest[] = $entry->id;
    }
}
```

It is **never** dispatched for the infrastructure defaults the constructor inserts directly (`Container::class`, `Psr\Container\ContainerInterface::class`, the dispatcher), nor for `extend()` — extending does not change the registration, it appends decorator state.

### `EntryResolved`

Dispatched after an entry has been built and **cached**, and only when *all* of the following hold:

- the entry is not infrastructure (`auto === false`),
- the entry is not an alias,
- the resolved value is an object.

```php
use Projek\Container\Events\EntryResolved;

// In a listener:
public function onEntryResolved(EntryResolved $event): void
{
    // Decorator / inflector pattern on the freshly built instance:
    if ($event->instance instanceof LoggerAwareInterface) {
        $event->instance->setLogger($this->logger);
    }
}
```

It is **never** dispatched on:

- **cache hits** — once an entry is built, later `get()` calls return the cached value silently;
- **`make()`** — on-the-fly resolution is deliberately event-free (it injects `ContainerAware` results directly, see [Container Awareness](Container-Awareness));
- builds of **`auto` entries** (the container's own defaults);
- **non-object results** (a factory returning an array, string, …);
- **aliases** — an alias resolves through its target's `get()`, so exactly **one** event fires, carrying the **target's** id.

## Performance & Cache Behavior

Because `EntryResolved` is dispatched strictly **after** the cache write, each entry notifies listeners exactly once:

- Listeners will not be notified on subsequent lookups of the same service; **`ContainerAware`** injection only happens during the very first resolution.
- A listener that throws does not un-cache the entry: the exception propagates untouched out of `set()` / `get()` (listener exceptions are user code), the instance stays cached, and the event is **not** replayed on the next `get()`.
- Events raised while the `EventDispatcherInterface` entry itself is being built for the first time (the dispatcher does not exist yet) are queued and flushed, in order, right after it is cached — a bootstrap detail with two accepted edge cases: a throwing listener during that flush propagates and drops the remaining queued events, and if the dispatcher's own build fails, the queue survives and flushes after a later successful build.

## What events can no longer do

- **No interception**: there is no "before" event, so listeners cannot redirect an id, swap a factory, or replace a resolved entry — payloads are readonly.
- **No mutation**: use [`extend()`](Extending-an-instance) to modify an entry's value, and `set()` / `replace`-via-`set()` to change registrations.

## Internal Listener Provider

The library includes `Projek\Container\Events\ListenerProvider` which handles core container features: it maps `EntryResolved` to an injection listener that calls `setContainer()` on any resolved `ContainerAware` instance. (`EntryRegistered` has no internal listener — it is a user-facing notification.)

If you provide your own Dispatcher, ensure you register this provider to maintain auto-injection support.

```php
$provider = new Projek\Container\Events\ListenerProvider();
$provider->setContainer($container);

// Register $provider in your custom Dispatcher...
```
