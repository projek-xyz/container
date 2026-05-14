# Create an instance of a class without registering it to the container stack.

```php
$container->make($callable[, $arguments|$condition[, $condition]]) mixed
```

Unlike `get()`, the `make()` method will **not** store the resolved instance in the container. Every time you call `make()`, it will return a new instance (unless the callable itself returns a shared instance).

| Parameters | Type | Description |
| --- | --- | --- |
| `$callable` | `string`, `callable` | `string` of class name or `callable` |
| `$arguments` | `array`, `\Closure` | **Optional**: pass an array to callback handler or conditionally resolve the callback |
| `$condition` | `\Closure` | **Optional**: conditionally resolve the callback |

## Usage

This method will always assume that the first argument is a callable, which means the return value of this method is the return value of the callable. 

```php
$container->make(SomeClass::class);
```

So, if `SomeClass` has an `__invoke()` method, it will return the value from `__invoke()` instead. Otherwise, it will return the class instance. Also, any arguments required for the `__construct()` and the `__invoke()` methods will be automatically injected if they're available in the container.

```php
class SomeClass {
    // Will call $container->get(Foo::class)
    public function __construct(Foo $foo) {
        // some codes
    }

    // Will call $container->get(Bar::class)
    public function __invoke(Bar $bar) {
        // some codes
    }
}

$container->make(SomeClass::class);
```

The 1st argument of `make()` behaves exactly the same as the [2nd argument of the `set()` method](Registering-an-instance#1-use-callable-string-or-array), which means you can do the following:

```php
class SomeClass {
    public function __invoke(Bar $bar) {
        return $bar;
    }

    public function otherMethod(Bar $bar) {
        return $bar;
    }
}

// Class name
$container->make(SomeClass::class); // returns instance or the return value of `__invoke()`.
// Method
$container->make('SomeClass::otherMethod'); // returns the value from `otherMethod`
$container->make(['SomeClass', 'otherMethod']); // returns the value from `otherMethod`
$container->make([new SomeClass, 'otherMethod']); // returns the value from `otherMethod`
```

### 1. Second argument is an array of the callable's arguments

Let's say we have something like this:

```php
class SomeClass {
    public function __invoke($value) {
        return $value;
    }
}

$foo = $container->make(SomeClass::class, ['the value']); // returns 'the value'
```
> [!NOTE]
> The second argument will only be passed to the callback, not the class constructor.

### 2. Passing a closure as a condition

By default, it will try to call the `__invoke()` method if available. However, in cases where we need to perform checks and call another method if a certain condition is met, we can do the following:

```php
use Psr\Http\Server\RequestHandlerInterface;

$container->make(SomeClass::class, function ($instance) {
    if ($instance instanceof RequestHandlerInterface) {
        return [$instance, 'handle'];
    }

    return null; // Accepts a falsy value or the $instance of the class
});
```
Returning an array as a class-method pair means the return value of `make()` will be the return value of the defined method.

> [!NOTE]
> The `Closure` on the last argument will only work if the first argument is a string class name or an object.

### 3. Combining the two options

In case we need to change the default callback and pass arguments, we can do the following:

```php
$container->make(SomeClass::class, ['the value'], function ($instance) {
    return [$instance, 'handle'];
});
```

See [#11](https://github.com/projek-xyz/container/pull/11) & [#12](https://github.com/projek-xyz/container/pull/12) for details.
