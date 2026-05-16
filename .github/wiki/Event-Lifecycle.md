# Event Lifecycle

The Projek Container provides an event lifecycle based on the **PSR-14 Event Dispatcher** standard. This enables developers to intercept and modify the container's behavior at key points during service registration and resolution.

## Architecture

This library provides the **Event Classes** and a **`ListenerProvider`** for internal features like `ContainerAware` injection. 

While the container includes a minimalist internal `EventDispatcher` to ensure core features work out-of-the-box, it is designed to be used with a full-featured PSR-14 implementation.

The developer can:
1.  **Use the default**: No configuration needed; `ContainerAware` injection works automatically.
2.  **Provide a custom dispatcher**: Pass a PSR-14 compliant `EventDispatcherInterface` (e.g., `symfony/event-dispatcher`) to the constructor or via `setEventDispatcher()`.

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
| `AfterRegistration` | After `Container::set()` | Dispatched once a service is registered. Allows modification of the entry. |
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

### `AfterRegistration`

Dispatched eagerly when a service is set. Useful for eager initialization or global factory wrapping.

```php
// In a listener:
public function onAfterRegistration(AfterRegistration $event): void
{
    $entry = $event->getEntry();
    
    if ($entry instanceof EagerServiceInterface) {
        // Eagerly initialize a service
        $entry->initialize();
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

Dispatched after the instance is created. This is the primary hook for "Setter Injection" or the **Decorator Pattern**.

```php
// In a listener:
public function onAfterResolution(AfterResolution $event): void
{
    $instance = $event->getEntry();
    
    // Example: Decorator Pattern
    if ($event->id === 'my.service') {
        $event->setEntry(new MyServiceDecorator($instance));
    }
    
    // Example: Inflector / Awareness
    if ($instance instanceof LoggerAwareInterface) {
        $instance->setLogger($this->logger);
    }
}
```

## Performance & Cache Behavior

The `BeforeResolution` and `AfterResolution` events are **only dispatched for non-cached entries**. Once a service is resolved and stored in the container's internal cache, subsequent `get()` calls return the cached instance directly.

- **`ContainerAware`** injection only happens during the very first resolution.
- Listeners will not be notified on subsequent lookups of the same service.

## Internal Listener Provider

The library includes `Projek\Container\Events\ListenerProvider` which handles core container features. If you provide your own Dispatcher, ensure you register this provider to maintain auto-injection support.

```php
$provider = new Projek\Container\Events\ListenerProvider();
$provider->setContainer($container);

// Register $provider in your custom Dispatcher...
```
