# Register an instance

```php
$container->set(string $id, mixed $factory): static
```

| Parameters | Type | Description |
| --- | --- | --- |
| `$id` | `string` | Name of the service |
| `$factory` | `Closure`, `string`, `array`, `object`, `EntryFactory` | What builds the entry — see [Accepted factory shapes](#accepted-factory-shapes) |

Registration is **lazy**: `set()` only classifies and stores the factory as an internal `Entry`. Nothing is constructed, no dependency is resolved, and no `ContainerAware` injection happens at registration time — all of that happens on first resolution (see [How resolution works](#how-resolution-works)).

Validation on the other hand is eager: an invalid factory, a duplicate id, or an unknown alias target throws `Projek\Container\InvalidArgumentException` right away, with a `Cannot register entry "%s": …` message. Nothing has been stored when that happens.

> [!NOTE]
> Since `set()` never builds anything, registration order between entries does not matter — only resolution order does — except for aliases, whose target must already be registered.

## Accepted factory shapes

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

The class part of a pair must be an **existing class** (validated at registration: class exists, method exists and is public). A static pair is called statically; a non-static pair resolves the class through the container first (registered entry if any, otherwise a freshly autowired instance).

> [!NOTE]
> You cannot use a registered *entry id* as the class part of a pair (`'foo::method'` where `foo` is an id, not a class, throws at registration). To call a method on an already registered entry, use [`make([id, 'method'])`](Create-an-instance).

### 2. Use closures, function names, or callable objects

```php
// Closure — the recommended Swiss-army factory: autowire through its parameters
$container->set('myService', function (SomeDependency $dep): MyService {
    return new MyService($dep);
});

// Plain function name
$container->set('id', 'someFunction');
```

**Callable objects** (any object with `__invoke()`) are factories too: they are stored **as-is** and invoked as-is on resolution — never re-instantiated, so their state is preserved:

```php
$container->set('id', new InvokableFactory($config));
```

### 3. Use a name of a class

```php
// String of an instantiable class name
$container->set('myService', SomeFactoryClass::class);
```

The entry is still lazy — `SomeFactoryClass` is constructed on the first `get()`, with its constructor parameters autowired. A class-name factory **always builds an instance**: even when the class is invokable, `__invoke()` is never implicitly called (see [Things you should be aware of](#things-you-should-be-aware-of)).

### 4. Use an instance (as a factory)

Plain objects are **not** factories — the container cannot "produce" a value from a value, so `$container->set('myService', new SomeClass)` throws `InvalidArgumentException`. Register the instance through a closure, or implement the `Projek\Container\EntryFactory` interface for the explicit door:

```php
// Or the explicit EntryFactory door
use Projek\Container\EntryFactory;
use Psr\Container\ContainerInterface;

$instance = new SomeClass($config);

// Closure wrapper
$container->set('myService', fn (): SomeClass => $instance);

$configFactory = new class($config) implements EntryFactory {
    public function __construct(private array $config) {}

    public function create(ContainerInterface $container): Config
    {
        return Config::fromArray($this->config);
    }
};

$container->set(Config::class, $configFactory);
```

> [!TIP]
> A closure that simply returns a pre-built object (`fn () => $instance`) also gets `ContainerAware` injection applied to that object when the entry is first resolved.

### 5. Use an existing entry (as an alias)

You can use the name of an **already registered** service as the `$factory` parameter.

```php
// Based on the example above
$container->set(CertainInterface::class, function (): SomeClass {
    return new SomeClass;
});

$container->set(AnotherInterface::class, CertainInterface::class); // OR
$container->set('someClass', CertainInterface::class);

// So you could access the instance of SomeClass with the following:
$container->get(CertainInterface::class); // OR
$container->get(AnotherInterface::class); // OR
$container->get('someClass');
```

An alias may target any string that is not itself a buildable factory — including **interface names, traits, abstract classes, and enums**, as long as that id is already registered:

```php
$container->set('driver', MySQLDriver::class);
$container->set(DriverInterface::class, 'driver'); // alias → 'driver'

$container->get(DriverInterface::class); // => the MySQLDriver instance
```

The target must pre-exist: registering an alias to an unknown id throws `Cannot register entry "%s": "%s" is neither a registered entry, an instantiable class, nor a function.` — that message is the typo catcher for interface names and misspelled ids.

### 6. What is rejected

`set()` throws `Projek\Container\InvalidArgumentException` when:

- the **id is already registered** (`Cannot register entry "%s": already registered.`) — there is no silent no-op anymore; use [`extend()`](Extending-an-instance) to modify an existing entry. The built-in infrastructure defaults (`Container::class`, `ContainerInterface::class`, resolver, dispatcher) are placeholders and *can* be replaced.
- the factory is a **plain object** without `__invoke()` (wrap it in a closure or `EntryFactory`, see above).
- the factory is a **string naming neither** a registered entry, an instantiable class, a function, nor a `Class::method` pair.
- the factory is anything else (int, float, `null`, `bool`, a malformed pair, …).
- a **by-reference parameter** is declared by a closure, function, callable object, or class constructor — such parameters must be provided by the caller and cannot be autowired.

## How resolution works

### Autowiring

The container automatically resolves parameters that the factory did not receive explicitly. For each missing parameter, in order:

1. **Class type-hints**: fetched from the container by type name (`get(Foo::class)`). If the type is not registered and the parameter has a default value, the default is used; if it has neither, resolution fails with a `NotFoundException` naming the missing type.
2. **Default values**: used whenever the container cannot provide the parameter (see step 1) — including builtin-typed parameters.
3. **Untyped parameters**: looked up in the container by their **parameter name** used as the service id.
4. Otherwise the resolution fails with a `ResolutionException` (or the `NotFoundException` of the missing class type).

```php
$container->set('dbHost', fn (): string => 'localhost');

$container->set('db', function ($dbHost) {
    // $dbHost is untyped → looked up as the entry id 'dbHost' → 'localhost'
    return new Database($dbHost);
});
```

> [!NOTE]
> Builtin-typed parameters (`string $dbHost`, `int $count`, …) are **not** looked up by parameter name — they resolve through their default value only (so `fn (int $count = 3)` keeps `3` instead of being shadowed by an unrelated entry named `count`). Use an *untyped* parameter for name-based lookup.
>
> By-reference parameters are never autowired: they must be provided explicitly.

### Caching (Shared Instances)

By default, every service registered in the container is a "shared" instance. This means the container will only resolve the service once and cache the result for subsequent calls to `get()`.

```php
$container->set('session', Session::class);

$one = $container->get('session');
$two = $container->get('session');

var_dump($one === $two); // bool(true)
```

[`make()`](Create-an-instance) bypasses this cache entirely, and [`extend()`](Extending-an-instance) never invalidates it — decorators are applied against (or before) the cached value, not by rebuilding it.

## Cloning the Container

Cloning a `Container` **copies the registrations** into a fresh collector — it does not share it:

- Every entry is cloned: ids, factories, decorators, and metadata carry over, but each clone's **singleton cache is reset**, so the clone rebuilds its entries on first use.
- The self-referential infrastructure defaults (`Container::class`, `Psr\Container\ContainerInterface::class`, `Psr\EventDispatcher\EventDispatcherInterface::class`) are re-pointed to the clone — auto-wiring and `ContainerAware` injection bind the **clone**, not the original.
- The shared handler is rebuilt lazily against the clone.
- Later `set()` / `extend()` calls on the clone do **not** affect the original (and vice versa).

> [!WARNING]
> Objects captured **inside** your factory closures are shared by reference — cloning rewrites entries, never closure bindings. A factory that `use`d the original container keeps capturing the original:

```php
$container->set('legacy', function () use ($container) {
    return new Thing($container); // still the ORIGINAL container after clone
});
```

## Things you should be aware of

* A class-name factory **always builds the class instance** — `get()` never implicitly invokes `__invoke()`, even for invokable classes:

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
$container->get(FooBar::class); // => a FooBar instance, NOT void
```

* If you want the `__invoke()` result as the entry value, register a closure factory (or call `make([FooBar::class, '__invoke'], $args)` for a one-off).
* Entry values must be usable as their declared dependencies: if a factory returns something its consumers do not type-hint, resolution of *those* entries fails with a `TypeError` from their own signatures — returning an unexpected value no longer corrupts other entries silently, but it is still worth registering a `Closure` that declares what it returns:

```php
$container->set('foobar', function (Foo $foo, Bar $bar): FooBar {
    return new FooBar($foo, $bar);
});
```
