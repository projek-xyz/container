# Remove an instance

```php
$container->unset(string $id): void
```

| Parameters | Type | Description |
| --- | --- | --- |
| `$id` | `string` | Name of the service |

## Usage

```php
// Based on example above
$container->has('id'); // returns true

$container->unset('id');

$container->has('id'); // returns false
```

**NOTES :**

- Since we don't have such a _alias_ feature (yet), the way we do is by [registering the existing entry with the new name](Registering-an-instance#3-use-existing-entry-as-an-alias). So we `unset`-ing one `$id` the other `$id` with the same instance will remain exists.

```php
$container->set(CertainInterface::class, SomeClass::class);
$container->set(AnotherInterface::class, CertainInterface::class);

$container->unset(CertainInterface::class);
$container->has(CertainInterface::class); // => false
$container->has(AnotherInterface::class); // => true

$container->get(AnotherInterface::class); // => instance of `SomeClass`
```
