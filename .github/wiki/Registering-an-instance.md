# Register an instance

```php
$container->set($abstract, $concrete)
```

| Parameters | Type | Description |
| --- | --- | --- |
| `$abstract` | `string` | Name of the service |
| `$concrete` | `callable`, `object` | Instance of the service |

There's few ways to register your services to the container as follow:

### 1. Use any [`callable`](https://www.php.net/manual/en/language.types.callable.php)

```php
// callable string of a function name
$container->set('myService', 'aFunctionName');

// callable string of a class::method pair
$container->set('myService', 'SomeClass::methodName');

// callable array as an object of class and method pair
$container->set('myService', [$classInstance, 'methodName']);

// callable array as a string of class name and method pair
$container->set('myService', [SomeClass::class, 'methodName']);
```

If registering a class-method pair (whether it's a `string` or `array`) it would work regardless the method is a static or not. Let say we have the following

```php
class SomeClass implements CertainInterface
{
    public static function staticMethod() {
        // some codes
    }

    public function nonStaticMethod() {
        // some codes
    }
}

$container->set(CertainInterface::class, 'SomeClass::staticMethod'); // OR
$container->set(CertainInterface::class, 'SomeClass::nonStaticMethod'); // OR
$container->set(CertainInterface::class, [SomeClass::class, 'staticMethod']); // OR
$container->set(CertainInterface::class, [SomeClass::class, 'nonStaticMethod']); // OR
$container->set(CertainInterface::class, [new SomeClass, 'staticMethod']); // OR
$container->set(CertainInterface::class, [new SomeClass, 'nonStaticMethod']);
```

### 2. Use object of class instance

```php
$container->set('myService', new SomeClass);
```

### 3. Use string of a class name

```php
// callable string of a class name
$container->set('myService', SomeFactoryClass::class);
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
