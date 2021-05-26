# Extending an instance

```php
$container->extend(string $id, Closure $callback): object
```

| Parameters | Type | Description |
| --- | --- | --- |
| `$id` | `string` | Name of the existing service |
| `$callback` | `Closure` | callback function |

## Usage

This handy method will allow you to extend the functionality of existing entry.

```php
$container->set('db', function (Config $config) {
    return new Database($config);
});

$container->set(SomeDriver::class, function (Config $config) {
    return new MyDriver($config);
});

$container->extend('db', function (Database $db, SomeDriver $driver): Database {
    $db->addDriver($driver);

    return $db;
});
```
**NOTE :**

- This method should only works if the returns type of the entry (`$id`) is an object, otherwise it will throw an error.
- The 1st argument of the callback is always the instance of the entry (`$id`), and the other arguments is the value from another entries.
- The callback should returns the same instance as the 1st argument of the callback.
