# Event Lifecycle

The Projek Container provides an event lifecycle that follows the **PSR-14 Event Dispatcher** standard. This allows listeners to intercept and modify behavior at key points.

## Usage

```php
$container = new Container($entries, $dispatcher);

// OR assign later:
$container->setEventDispatcher($dispatcher);
```

## Events

| Event | When | Description |
| --- | --- | --- |
| `BeforeRegistration` | Before `Container::set()` | Allows listeners to modify the factory before registration. |
| `AfterRegistration` | After `Container::set()` | Provides access to the resolved entry after registration. |
| `BeforeResolution` | Before `Container::get()` | Allows listeners to modify or redirect the service ID before resolution. |
| `AfterResolution` | After `Container::get()` | Provides access to the resolved entry. Used by default to inject container into `ContainerAware` instances. |

### Important: Cache Behavior

The `BeforeResolution` and `AfterResolution` events are **only dispatched for non-cached entries**. When a service is resolved and cached in `$handledEntries`, subsequent `get()` calls return the cached instance without dispatching `AfterResolution`. This means:

- `ContainerAware` injection only happens on first resolution.
- Listeners won't be notified on cached entry lookups.

### `BeforeRegistration`

```php
class BeforeRegistration
{
    public string $id;
    public function setFactory(array|callable|string $factory): void;
    public function getFactory(): array|callable|string;
}
```

Dispatched before a service is registered. Allows listeners to modify the factory:

```php
// In a listener:
if ($event->id === 'foo') {
    $event->setFactory(fn () => 'modified');
}
```

### `AfterRegistration`

```php
class AfterRegistration
{
    public string $id;
    public function getEntry(): callable|object;
}
```

Dispatched after registration. Provides access to the resolved entry:

```php
// In a listener:
$entry = $event->getEntry();
```

### `BeforeResolution`

```php
class BeforeResolution
{
    public string $id;
}
```

Dispatched before resolution. Allows service ID redirection:

```php
// In a listener:
$event->id = 'baz'; // redirect to 'baz'
```

### `AfterResolution`

```php
class AfterResolution
{
    public string $id;
    public function getEntry(): callable|object;
}
```

Dispatched after resolution. Used by default `ListenerProvider` to inject container into `ContainerAware` instances:

```php
// Default ListenerProvider behavior:
if ($entry instanceof ContainerAware && $event->id !== ContainerInterface::class) {
    $entry->setContainer($container);
}
```

### `setEventDispatcher()`

```php
public function setEventDispatcher(EventDispatcherInterface $dispatcher): self
```

Assign or swap an event dispatcher implementation after construction.

### Limitations

- `make()` does not dispatch any events.
- `extend()` does not dispatch any events (it calls `get()` which dispatches events).
- Cached entries bypass `AfterResolution` event.

## Default Listener Provider

The `ListenerProvider` class provides default listeners for all four events:

```php
$provider = new ListenerProvider();
$provider->setContainer($container);
$dispatcher = new YourDispatcher($provider);
$container->setEventDispatcher($dispatcher);
```

The `ListenerProvider` implements `ContainerAware` and automatically injects the container into `ContainerAware` instances during `AfterResolution`.
