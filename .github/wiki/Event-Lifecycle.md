# Event Lifecycle

The Projek Container provides an event lifecycle based on the **PSR-14 Event Dispatcher** standard. This enables developers to intercept and modify the container's behavior at key points during service registration and resolution.

## Architecture

It is important to understand that while this library provides the **Event Classes** and a **`ListenerProvider`**, it does **not** include a full `EventDispatcher` implementation. 

The developer is responsible for:
1.  Providing a PSR-14 compliant `EventDispatcherInterface` implementation (e.g., `symfony/event-dispatcher`).
2.  Instantiating the dispatcher.
3.  Passing the dispatcher to the `Container` constructor or via `setEventDispatcher()`.

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

| Event | When | Description |
| --- | --- | --- |
| `BeforeRegistration` | Before `Container::set()` | Allows modification of the factory/class before it is registered. |
| `AfterRegistration` | After `Container::set()` | Dispatched once a service is registered and its entry resolved. |
| `BeforeResolution` | Before `Container::get()` / `make()` | Allows listeners to modify or redirect the service ID before resolution starts. |
| `AfterResolution` | After `Container::get()` / `make()` | Dispatched after a service is successfully resolved. |

### `BeforeRegistration`

Allows you to swap a factory or class name before the container stores it.

```php
// In a listener:
public function onBeforeRegistration(BeforeRegistration $event): void
{
    if ($event->id === 'some.service') {
        // Redirect to a different factory
        $event->setFactory(fn() => new AlternativeService());
    }
}
```

### `BeforeResolution`

Used for dynamic service redirection or aliasing.

```php
// In a listener:
public function onBeforeResolution(BeforeResolution $event): void
{
    if ($this->shouldUseExperimental()) {
        // Intercept 'db' and redirect to experimental driver
        $event->id = ExperimentalDriver::class;
    }
}
```

### `AfterResolution`

Dispatched after the instance is created. This is the primary hook for "Setter Injection" or "Inflector" patterns.

```php
// In a listener:
public function onAfterResolution(AfterResolution $event): void
{
    $instance = $event->getEntry();
    
    if ($instance instanceof LoggerAwareInterface) {
        $instance->setLogger($this->logger);
    }
}
```

## Performance & Cache Behavior

The `BeforeResolution` and `AfterResolution` events are **only dispatched for non-cached entries**. Once a service is resolved and stored in the container's internal cache, subsequent `get()` calls return the cached instance directly to ensure high performance.

- **`ContainerAware`** injection only happens during the very first resolution.
- Listeners will not be notified on subsequent lookups of the same service.

## Internal Listener Provider

The library includes `Projek\Container\Events\ListenerProvider` which handles core container features like `ContainerAware` injection. If you provide your own Dispatcher, ensure you register this provider if you wish to maintain auto-injection support.

```php
$provider = new Projek\Container\Events\ListenerProvider();
$provider->setContainer($container);

// Register $provider in your custom Dispatcher...
```
