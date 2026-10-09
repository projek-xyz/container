# Create an instance of a class without registering it to the container stack.

```php
$container->make(array|callable|object|string $instance, array $args = []): mixed
```

Unlike `get()`, the `make()` method will **not** store anything in the container: every call builds a **fresh** value, no entry is registered (`has()` stays `false`), and **no events are dispatched**. `ContainerAware` results still get the container injected — directly, without going through the event system.

| Parameters | Type | Description |
| --- | --- | --- |
| `$instance` | `string`, `callable`, `array`, `object` | What to build: a registered id, a class name, or a callable shape |
| `$args` | `array` | **Optional**: arguments for the constructor or invocation (positional by order, or named) |

## Usage

`make()` accepts exactly **four families** of input — anything else throws `Projek\Container\InvalidArgumentException`:

1. **A registered id** (string) — builds that entry fresh: the factory runs and `extend()` decorators are applied, aliases are followed to their target. Still never cached, still no events.
2. **An instantiable class-name string** (unregistered, no `::`) — a fresh instance of the class; `$args` feeds the **constructor**, and unprovided parameters are autowired.
3. **A valid callable shape** — a `Closure`, an object with `__invoke()`, a function name, a `Class::method` string, or a `[class-string|object, method]` pair. Returns whatever the callable returns; `$args` are its arguments.
4. **Anything else** — throws (plain objects, interface/trait/abstract-class names that are not registered ids, malformed arrays, …).

```php
$container->make(SomeClass::class);                  // fresh instance, constructor autowired
$container->make(SomeClass::class, [$dependency]);   // $dependency feeds the constructor
$container->make(SomeClass::class, ['name' => 'x']); // named arguments also work
$container->make('SomeClass::handle');               // returns handle()'s return value
$container->make([new SomeClass, 'handle']);         // ditto, from an object pair
$container->make('my.service');                      // registered entry, built fresh + decorated
```

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

$container->make(SomeClass::class); // => the SomeClass INSTANCE
```

> [!NOTE]
> A class-name string **always builds the instance**, even when the class has an `__invoke()` — it is never implicitly invoked. Use `make([SomeClass::class, '__invoke'], $args)` if you want the invoke result.

### Arguments

`$args` is typed `array` (a non-array now raises a native `TypeError`). Where they land depends on the family:

| Input | `$args` goes to |
| --- | --- |
| a class entry — an id registered from a class name, or an unregistered class name | the **constructor** (autowiring fills whatever you skip) |
| a `Closure`, function, `Class::method`, pair, or invokable object — whether passed directly or registered under an id | the **invocation** (`call_user_func_array` semantics, named keys included) |
| an `EntryFactory` entry | ignored — `create($container)` takes no arguments |

```php
class SomeClass {
    public function __invoke($value) {
        return $value;
    }

    public function otherMethod($value) {
        return $value;
    }
}

$container->make(SomeClass::class, ['the value']);                // feeds __construct() — NOT __invoke()
$container->make([SomeClass::class, '__invoke'], ['the value']);  // feeds __invoke()
$container->make([SomeClass::class, 'otherMethod'], ['the value']);
$container->make([new SomeClass, 'otherMethod'], ['the value']);
```

## Error handling

`make()` runs through the **same error boundary as `get()`**:

- a missing autowired dependency surfaces as a `Projek\Container\NotFoundException` naming the **missing** id;
- package/resolver failures are wrapped as `Projek\Container\ResolutionException` with a `Failed to resolve "%s": %s` message;
- your own exceptions and native `\Error`s propagate **untouched**;
- input that matches none of the four families throws `Projek\Container\InvalidArgumentException` (`Cannot make from "%s": %s`).

## Migrating from v1.x

- **The `$condition` parameter is gone.** Conditional method selection is now spelled as a pair:
  ```php
  // v1
  $container->make(SomeClass::class, $args, fn ($instance) => [$instance, 'handle']);
  // v2
  $container->make([SomeClass::class, 'handle'], $args);
  ```
- **`$args` must be an `array`** — passing a `Closure` (the old condition form) or any non-array is now a `TypeError`.
- **`make(SomeClass::class, $args)` feeds the constructor**, not `__invoke()` (v1 invoked `__invoke($args)` for invokable classes):
  ```php
  // v1
  $container->make(SomeClass::class, ['the value']);
  // v2
  $container->make([SomeClass::class, '__invoke'], ['the value']);
  ```
- **The acceptance contract is strict**: unknown strings (e.g. an unregistered interface name) used to be attempted blindly and now throw `InvalidArgumentException`.
- **Plain objects now throw** (v1 returned them as-is): wrap them — `make(fn () => $instance)` — or just use the object directly.
