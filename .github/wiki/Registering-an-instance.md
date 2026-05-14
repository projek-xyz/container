# Register an instance

```php
$container->set(string $id, $entry): static
```

| Parameters | Type | Description |
| --- | --- | --- |
| `$id` | `string` | Name of the service |
| `$entry` | `callable`, `object` | Instance of the service |

## Usage

There are a few ways to register your services to the container, as follows:

### 1. Use [`callable`](https://www.php.net/manual/en/language.types.callable.php) string or array

```php
// callable string of a function name
$container->set('id', 'functionName');

// callable string of a class::method pair
$container->set('id', 'ClassName::methodName');

// callable array as an object of class and method pair
$container->set('id', [$classInstance, 'methodName']);

// callable array as a string of class name and method pair
$container->set('id', [ClassName::class, 'methodName']);
```

By passing the 2nd argument as a class-method pair (whether it's a `string` or `array`), it will work regardless of whether the method is static or not. Let's say we have the following:

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

### 2. Use an instance or name of a class

```php
// Instance of class
$container->set('myService', new SomeClass);

// String of class name
$container->set('myService', SomeFactoryClass::class);
```

### 3. Use an existing entry (as an alias)

You can use the name of the registered service as the `$entry` parameter.

```php
// Based on the example above
$container->set(CertainInterface::class, function () {
    return new SomeClass;
});

$container->set(AnotherInterface::class, CertainInterface::class);
$container->set('someClass', CertainInterface::class);

// So you could access the instance of SomeClass with the following:
$container->get(CertainInterface::class); // OR
$container->get(AnotherInterface::class); // OR
$container->get('someClass');
```

That said, we also have the option to register a method from an existing container entry, as follows:

```php
class SomeClass implements CertainInterface
{
    public function theMethod() {
        return 'a value';
    }
}

$container->set(CertainInterface::class, SomeClass::class);
$container->set('foo', function (CertainInterface $bar) {
    return $bar;
});

// Because the container 'foo' technically returns the instance of CertainInterface
$container->set('bar', 'foo::theMethod');                       // => returns 'a value'

// Because the CertainInterface is registered as a container entry
$container->set('baz', [CertainInterface::class, 'theMethod']); // => returns 'a value'
```

## How resolution works

### Autowiring

The container automatically resolves dependencies for constructors and callables. This is known as autowiring.

1.  **Class-based dependencies**: If a parameter is type-hinted with a class or interface name, the container will try to fetch that service from itself.
2.  **Named dependencies**: If a parameter is a builtin type (e.g., `string`, `int`) or untyped, the container will use the **parameter name** as the service identifier to fetch from the container.

```php
$container->set('dbHost', 'localhost');

$container->set('db', function (string $dbHost) {
    // $dbHost will be 'localhost' because it matches the parameter name
    return new Database($dbHost);
});
```

### Caching (Shared Instances)

By default, every service registered in the container is a "shared" instance. This means the container will only resolve the service once and cache the result for subsequent calls to `get()`.

```php
$container->set('session', Session::class);

$one = $container->get('session');
$two = $container->get('session');

var_dump($one === $two); // bool(true)
```

## Cloning the Container

When you clone a `Container` instance, it creates a fresh `Resolver` instance. However, it **shares** the same internal entries and factories. This is useful for creating a child container that inherits the existing services but can have its own resolution context if needed.

## Things you should be aware of

* By registering an entry this way, the container will check whether it's a callable class or not.
* If it's a callable class, then the `Container::get()` method will return the value returned by the `__invoke()` method instead of the instance of the class.

Let's say you have the following class:

```php
class FooBar {
    protected $foo;

    public function __construct(Foo $foo) {
        $this->foo = $foo;
    }

    /**
     * The __invoke method returns void.
     */
    public function __invoke(Bar $bar): void {
        $this->foo->setBar($bar);
    }
}

$container->set(FooBar::class, FooBar::class);

// What you'll get:
$container->get(FooBar::class); // => returns void
```

That said, it's possible to have an entry that returns an unexpected value. This will lead to an error when you try to register a new entry and require the _invalid_ entry as a dependency.

```php
$container->set('foo', function (FooBar $foobar) {
    // the codes.
});
```

When the container tries to resolve the `foo` entry, it will fetch the `FooBar` entry and inject it into the callback, so a `TypeError` will be thrown.

So it is recommended to always use a `Closure` as an entry factory and inject the required dependencies through its arguments.

```php
$container->set('foobar', function (Foo $foo, Bar $bar) {
    return new FooBar($foo, $bar);
});
```
