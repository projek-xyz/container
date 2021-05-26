# Register an instance

```php
$container->set(string $id, $entry): static
```

| Parameters | Type | Description |
| --- | --- | --- |
| `$id` | `string` | Name of the service |
| `$entry` | `callable`, `object` | Instance of the service |

## Usage

There's few ways to register your services to the container as follow:

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

By passing 2nd argument as a class-method pair (whether it's a `string` or `array`) it would work regardless the method is a static or not. Let say we have the following

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

### 2. Use instance or name of a class

```php
// Instance of class
$container->set('myService', new SomeClass);

// String of class name
$container->set('myService', SomeFactoryClass::class);
```

### 3. Use existing entry (as an alias)

you can use name of the registered service as the `$concrete` parameter.
```php
// Based on example above
$container->set(CertainInterface::class, function () {
    return new SomeClass;
});

$container->set(AnotherInterface::class, CertainInterface::class);
$container->set('someClass', CertainInterface::class);

// So you could access instance of SomeClass with the following
$container->get(CertainInterface::class); // OR
$container->get(AnotherInterface::class); // OR
$container->get('someClass');
```

That said, we also have the option to register a method from existing container as follow

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

// Because the container 'foo' is technically returns the instance of CertainInterface
$container->set('bar', 'foo::theMethod');                       // => returns 'a value'

// Because the CertainInterface is registered as a container 
$container->set('baz', [CertainInterface::class, 'theMethod']); // => returns 'a value'
```

## Things you should aware of

* By registering an entry this way, the container will check whether it's a callable class or not.
* If it's a callable class, then the `Container::get()` method will returns any returns value from the `__invoke()` method instead of the instance of the class.

Let say you have the following class

```php
class FooBar {
    protected $foo

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

// What you'll get
$container->get(FooBar::class); // => returns void
```

That said, it's possible to have an entry that returns unexpected value, this will led you to an error when you trying to register a new entry and require the _invalid_ entry as dependency.

```php
$container->set('foo', function (FooBar $foobar) {
    // the codes.
});
```

When the container trying to resolve `foo` entry, it will fetch `FooBar` entry and inject it to the callback, so you'll got `TypeError` thrown.

So its recommended to always use `Closure` as an entry factory and injecting the required dependencies from its arguments.

```php
$container->set('foobar', function (Foo $foo, Bar $bar) {
    return new FooBar($foo, $bar);
});
```
