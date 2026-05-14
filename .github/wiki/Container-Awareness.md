# Container Awareness

The Projek Container provides a way to automatically inject the container instance into your services using the `ContainerAware` interface and `HasContainer` trait.

## Usage

To make a class "container aware," implement the `Projek\Container\ContainerAware` interface. You can use the `Projek\Container\HasContainer` trait to provide the default implementation.

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

When you register `MyService` in the container using `$container->set()`, the container will automatically call `setContainer($this)` on the instance.

```php
$container->set(MyService::class, MyService::class);

$service = $container->get(MyService::class);
// $service->getContainer() will return the $container instance
```

## The `getContainer()` method

The `HasContainer` trait provides a flexible `getContainer()` method:

1.  **Without arguments**: Returns the `Psr\Container\ContainerInterface` instance.
2.  **With a string argument**: Treats the argument as a service ID and returns the corresponding service from the container (equivalent to `$container->get($id)`).

```php
// Returns the container itself
$container = $this->getContainer();

// Returns the 'logger' service from the container
$logger = $this->getContainer('logger');
```

## Why use Container Awareness?

While dependency injection through the constructor is generally preferred (and supported by the container's autowiring), Container Awareness can be useful for:

- **Optional dependencies**: When a service might need access to many other services but only in specific scenarios.
- **Service Locators**: In some architectural patterns where a class needs to dynamically fetch services.
- **Legacy integration**: When you cannot easily change a class constructor.
