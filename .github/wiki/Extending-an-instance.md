# Extending an instance

```php
$container->extend(string $id, Closure $callback): static
```

| Parameters | Type | Description |
| --- | --- | --- |
| `$id` | `string` | Name of the existing service |
| `$callback` | `Closure` | Decorator that receives the current value and returns the new one |

This handy method allows you to extend the functionality of an existing entry by appending a **decorator** — it never forces a build, never replaces the factory, and returns the container (`static`), so calls can be chained.

```php
$container->set('db', function (Config $config): Database {
    return new Database($config);
});

$container->set(SomeDriver::class, function (Config $config): SomeDriver {
    return new MyDriver($config);
});

$container->extend('db', function (Database $db, SomeDriver $driver): Database {
    $db->addDriver($driver);

    return $db;
});
```

The decorator receives the current value of the entry as its **first argument**; any further declared parameters are autowired from the container, exactly like a factory's parameters.

## When the decorator runs

`extend()` is deliberately lazy and cache-safe:

- **Entry not built yet**: the callback is queued on the entry and runs inside the entry's next build — triggered by `get()` *or* [`make()`](Create-an-instance) — before the value is cached.
- **Entry already built**: the callback runs immediately against the cached value, and *on success* the result replaces the cached value. This is **apply-then-append**: the callback only joins the decorator list after it ran successfully, so the list and the cached value can never drift. If the callback throws, nothing is appended, the cached value stands, and your exception propagates untouched.

Because `make()` applies decorators too, an entry extended before its first `get()` behaves consistently on both paths.

## The return-type contract

`extend()` validates everything up front — failures are `Projek\Container\InvalidArgumentException`, except a missing `$id`, which is a `NotFoundException` (same as `get()`):

1. **The entry must exist.** Extending an unregistered id throws.
2. **The entry's extension target type must be derivable** — otherwise `Cannot extend entry "%s": extension target type is not derivable.`:
   - a class-name entry → the class itself;
   - an `EntryFactory` entry → any object type is acceptable;
   - a closure / function / method-pair entry → its declared **return type must be a single named class type** (for a callable object: the return type of `__invoke()`);
   - an alias entry → follows the alias chain to its target;
   - no return type, a builtin-only return type, a union/intersection, or a dead-end alias → not derivable, throws.
3. **The callback must declare an explicit, non-union, named return type** — `mixed`, a union, a builtin, or no return type at all throws `Cannot extend entry "%s": callback must declare an explicit, non-union, named return type.`
4. **The declared return type must be the target type or a subclass of it** — otherwise `Cannot extend entry "%s": callback must return "…"`.

```php
$container->set('db', function (Config $config): Database {
    return new Database($config);
});

// OK: DecoratingDatabase extends Database
$container->extend('db', function (Database $db): DecoratingDatabase {
    return new DecoratingDatabase($db);
});

// Throws: target is Database, the callback declares something else
$container->extend('db', function (Database $db): Logger {
    return new Logger();
});
```

> [!NOTE]
> - The callback does **not** have to return the same instance it received — returning the target type or any subclass of it is fine (that is the decorator pattern).
> - At apply time the declared return type is enforced natively by PHP: a `TypeError` means your callback returned the wrong thing and propagates untouched.
> - For an already-built entry, a throwing or type-mismatched decorator leaves the entry exactly as it was — fix the callback and call `extend()` again.
