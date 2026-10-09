# Container Awareness

The Projek Container provides a mechanism to automatically inject the container instance into your services using the `ContainerAware` interface and `HasContainer` trait.

## Usage

To make a class "container aware," implement the `Projek\Container\ContainerAware` interface. You can use the `Projek\Container\HasContainer` trait to provide a standard implementation.

```php
use Projek\Container\ContainerAware;
use Projek\Container\HasContainer;

class MyService implements ContainerAware
{
    use HasContainer;

    public function doSomething()
    {
        // Access the container instance
        $container = $this->getContainer();
        
        // Or fetch another service from the container
        $otherService = $this->getContainer(OtherService::class);
    }
}
```

## How it works (Zero-Config)

Container Awareness is powered by the **PSR-14 Event System** for `get()`, and by direct injection for `make()`.

When an entry is resolved through `get()` for the **first time**, the container dispatches an `EntryResolved` event (after the value is cached). The internal `ListenerProvider` listens for this event and calls `setContainer($this)` on any instance implementing the `ContainerAware` interface. Cache hits dispatch no events — but the instance already carries its container from the first resolution.

Results from [`make()`](Create-an-instance) are injected **directly**, without dispatching any event — `make()` is deliberately event-free, and its injection works no matter which dispatcher (if any) is configured.

### Using a Custom Dispatcher

If you provide your own PSR-14 `EventDispatcher`, you must ensure that the container's internal `ListenerProvider` is registered if you want to keep automatic `ContainerAware` injection working for `get()`:

```php
use Projek\Container\Events\ListenerProvider;

$provider = new ListenerProvider();
$provider->setContainer($container);

// Add $provider to your custom dispatcher's listener stack...
```

> [!WARNING]
> Without this wiring, a custom dispatcher silently drops the injection listener — `ContainerAware` instances resolved through `get()` keep a `null` container. (`make()` injection is unaffected.)

## The `getContainer()` method

The `HasContainer` trait provides a flexible `getContainer()` method:

1.  **Without arguments**: Returns the `Psr\Container\ContainerInterface` instance.
2.  **With a string argument**: Treats the argument as a service ID and returns the corresponding service from the container (equivalent to calling `$container->get($id)`).

```php
// Returns the container itself
$container = $this->getContainer();

// Returns the 'logger' service from the container
$logger = $this->getContainer('logger');
```

## Why use Container Awareness?

While dependency injection through the constructor (autowiring) is generally preferred, Container Awareness is useful for:

- **Optional dependencies**: When a service might need access to many other services but only in specific scenarios.
- **Service Locators**: In architectural patterns where a class needs to dynamically fetch services.
- **Legacy integration**: When you cannot easily change a class constructor.
