# `Container::unset($abstract)`

| Parameters | Type | Description |
| --- | --- | --- |
| `$abstract` | `string` | Name of the service |

**Usage**

```php
// Based on example above
$container->has('myService'); // returns true

$container->unset('myService');

$container->has('myService'); // returns false
```