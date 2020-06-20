## `Container::make($callable[, $arguments|$condition[, $condition]])`

| Parameters | Type | Description |
| --- | --- | --- |
| `$callable` | `string`, `callable` | `string` of class name or `callable` |
| `$arguments` | `array`, `\Closure` | **Optional**: pass an array to callback handler or conditionally resolve the callback |
| `$condition` | `\Closure` | **Optional**: conditionally resolve the callback |

## Usage

```php
// Treat 2nd parameter as arguments
$container->make(SomeClass::class, ['a value']);

// Treat 2nd parameter as condition
$container->make(SomeClass::class, function ($instance) {
    if ($instance instanceof CertainInterface) {
        return [$instance, 'theMethod'];
    }

    return null; // Accepts falsy or $instance of the class
});

// Treat 2nd parameter as arguments and 3rd one as condition
$container->make(SomeClass::class, ['a value'], function ($instance) {
    // a condition
});
```

## Notes:

- If `SomeClass` is a callable, the value from 2nd parameter will passed to `__invoke` method and `make()` will returns the return value from `__invoke` method. Otherwise, the value from 2nd parameter will be ignored and `make()` will returns the instance of `SomeClass`.
- The 1st parameter accepts `string` or `callable`, means you can have the following:
    ```php
    class SomeClass {
        public function __invoke(Bar $bar) {
            return $bar;
        }

        public function theMethod(Bar $bar) {
            return $bar;
        }
    }

    // Class name
    $container->make(SomeClass::class); // returns instance or the return value of `__invoke`.
    // Method
    $container->make('SomeClass::theMethod'); // returns the value from `theMethod
    $container->make(['SomeClass', 'theMethod']); // returns the value from `theMethod
    $container->make([new SomeClass, 'theMethod']); // returns the value from `theMethod
    ```
- The 2nd parameter could be `array` of `$arguments` or `Closure` of `$condition`
    ```php
    class SomeClass {
        public function theMethod(Foobar $foobar) {
            return $foobar;
        }
    }

    $container->make(SomeClass::class, ['value']); // The $arguments will be ignored
    $container->make('SomeClass::theMethod', [new Foobar]); // The `theMethod` will get the instance of `Foobar` class
    ```
- If no `$arguments` provided, the container will try to resolve the required parameter(s) from registered container.
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
- The 3rd parameter should be `Closure` of `$condition`. In this case you need to invoke another method if it have certain condition otherwise it will fallback to default `__invoke` 
    ```php
    class SomeClass implements CertainInterface {
        public function __invoke(Bar $bar) {
            return $bar;
        }

        public function theMethod(Foobar $foobar) {
            return $foobar;
        }
    }

    $container->make(SomeClass::class, function ($instance) {
        if ($instance instanceof CertainInterface) {
            return [$instance, 'theMethod'];
        }

        return null; // Accepts falsy or $instance of the class
    });
    ```

See [#11](https://github.com/projek-xyz/container/pull/11) & [#12](https://github.com/projek-xyz/container/pull/12) for details.
