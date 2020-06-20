# Register an instance

```php
$container->set($abstract, $concrete)
```

| Parameters | Type | Description |
| --- | --- | --- |
| `$abstract` | `string` | Name of the service |
| `$concrete` | `callable`, `object` | Instance of the service |

You have few ways registering your services to the container. Example above you can use `Closure`, you also has the options to:

## Use any [`callable`](https://www.php.net/manual/en/language.types.callable.php)

```php
// callable string of a function name
$container->set('myService', 'aFunctionName');

// callable string of a static method of a class
$container->set('myService', 'MyServiceProvider::staticMethodName');

// callable array as a method of class instance
$container->set('myService', [$myObj, 'methodName']);

// callable array as a static method of class
$container->set('myService', [MyServiceProvider::class, 'staticMethodName']);
```

## Use object of class instance

```php
$container->set('myService', new MyService);
```

## Use string of a class name

```php
// callable string of a class name
$container->set('myService', MyServiceProvider::class);
```

By registering a service as class name you have the option to resolve and inject the dependencies either for its `__construct()` and `__invoke()` method, if any. See [#2](https://github.com/projek-xyz/container/pull/2)

```php
class FooBarProvider {
    protected $foo

    /**
     * The constructor dependency will be injected and the class will be initiated on register `set()`.
     * 
     * @param Foo $foo
     */
    public function __construct(Foo $foo) {
        $this->foo = $foo;
    }

    /**
     * The dependency will be injected on retrieval `get()`
     * and the returns value will be available for the `serviceId` instead of the class instance.
     * 
     * @param Bar $bar
     * @return FooBar
     */
    public function __invoke(Bar $bar) {
        return $this->foo->bar($bar);
    }
}

/**
 * So when you register a service like this.
 */
$container->set(FooBarInterface::class, FooBarProvider::class);

/**
 * You'll get instance of `FooBar` instead of `FooBarProvider`
 */
$fooBar = $container->get(FooBarInterface::class);
```
## `set()` an alias of existing service

you can use name of the registered service as the `$concrete` parameter.
```php
// Based on example above
$container->set(CertainInterface::class, SomeClass::class);
$container->set(AnotherInterface::class, CertainInterface::class);
$container->set('someClass', CertainInterface::class);

// So you could access instance of SomeClass with the following
$container->get(CertainInterface::class); // OR
$container->get(AnotherInterface::class); // OR
$container->get('someClass');
```
