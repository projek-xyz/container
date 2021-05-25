# Create an instance of class without register it to the container stack.

```php
$container->make($callable[, $arguments|$condition[, $condition]]) mixed
```

| Parameters | Type | Description |
| --- | --- | --- |
| `$callable` | `string`, `callable` | `string` of class name or `callable` |
| `$arguments` | `array`, `\Closure` | **Optional**: pass an array to callback handler or conditionally resolve the callback |
| `$condition` | `\Closure` | **Optional**: conditionally resolve the callback |

## Usage

This method will always asumed that the first argument is a callable, which means the returns value of this method is the returns value of the callable. 

```php
$container->make(SomeClass::class);
```

So, if the `SomeClass` has `__invoke()` method it will returns value from `__invoke()` instead. Otherwise, it will returns the class instance. Also, any arguments required for the `__construct()` and the `__invoke()` method will automatically injected if they're available in the container.

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

The 1st argument of `make()` would behave exactly the same as [2nd argument of the `set()` method](Registering-an-instance#1-use-callable-string-or-array), means you can have the following:

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
$container->make(SomeClass::class); // returns instance or the return value of `__invoke`.
// Method
$container->make('SomeClass::otherMethod'); // returns the value from `otherMethod
$container->make(['SomeClass', 'otherMethod']); // returns the value from `otherMethod
$container->make([new SomeClass, 'otherMethod']); // returns the value from `otherMethod
```

### 1. Second argument an array of the callable arguments

Let say, we have something like this.

```php
class SomeClass {
    public function __invoke($value) {
        return $value;
    }
}

$foo = $container->make(SomeClass::class, ['the value']); // return 'the value'
```
**NOTE :**
- The second argument will only be passed to the callback, not the class constructor.

### 2. Passing a closure as condition

By default it will try to call `__invoke()` method if available, but in some cases if we need to perform some checks and call another method if it meet certain condition, we could do by the following.

```php
use Psr\Http\Server\RequestHandlerInterface;

$container->make(SomeClass::class, function ($instance) {
    if ($instance instanceof RequestHandlerInterface) {
        return [$instance, 'handle'];
    }

    return null; // Accepts falsy or $instance of the class
});
```
By returning an array of class-method pair means the returns value of `make()` will be the returns value of defined method.

**NOTE :**
- The `Closure` on the last argument will only works if the first argument is a string class name or an object.

### 3. Combine the two options

In case we need to change default callback and passing the arguments, we could do by the following.

```php
$container->make(SomeClass::class, ['the value'], function ($instance) {
    return [$instance, 'handle'];
});
```

See [#11](https://github.com/projek-xyz/container/pull/11) & [#12](https://github.com/projek-xyz/container/pull/12) for details.
