[![Lisence](https://img.shields.io/packagist/l/projek-xyz/container?style=flat-square)](https://github.com/projek-xyz/slim-plates/blob/master/LICENSE.md)
[![Actions Status](https://img.shields.io/github/workflow/status/projek-xyz/container/Tests/master?style=flat-square)](https://github.com/projek-xyz/container/actions)
[![Coverage Status](https://img.shields.io/coveralls/github/projek-xyz/container/master?style=flat-square)](https://coveralls.io/github/projek-xyz/container)
[![Coverage Status](https://img.shields.io/codeclimate/coverage/projek-xyz/container?style=flat-square)](https://codeclimate.com/github/projek-xyz/container)
[![Maintainability](https://img.shields.io/codeclimate/coverage-letter/projek-xyz/container?label=maintainability&style=flat-square)](https://codeclimate.com/github/projek-xyz/container/maintainability)

# Simple yet Flexible PSR-11 Container Implementation [![Lisence](https://img.shields.io/packagist/v/projek-xyz/container?style=flat-square)](https://packagist.org/packages/projek-xyz/container)

This tiny library aims to provide dead simple PSR-11 implementation with flexible service registration.

## Installation

Use [Composer](https://getcomposer.org/)

```bash
$ composer require projek-xyz/container --prefer-dist
```

## Usage

Define your `services.php` file

```php
return [
    /**
     * Let say you have your own config factory class, you can register it as an instance of class.
     */
    My\ConfigInterface::class => My\ConfigFactory::loadFile('path/to/config.php'),

    /**
     * Then you want to configure your logger based on configuration file you already loaded. 
     */
    Psr\Log\LoggerInterface::class => function (My\ConfigInterface $config) {
        $logConfig = $config->get('logger');

        return new Monolog\Logger(
            $logConfig['name'],
            $logConfig['handlers'],
            $logConfig['processors']
        );
    },

    /**
     * Then you want to configure your database connection and assign a logger interface. 
     */
    'db' => function (My\ConfigInterface $config, Psr\Log\LoggerInterface $logger) {
        $db = new My\Database\Connection(
            $config->get('database')
        );

        $db->setLogger($logger);

        return $db;
    },

    /**
     * You could also fetch an instance by simply reference the container name to fetch its instance.
     */
    'appSettings' => function ($db) {
        $db->query('SELECT * FROM app_settings');

        return $db->fetch()
    }
];

```

Now time to initiate the services

```php
$container = new Projek\Container(require 'path/to/services.php');

// now you have access to all
$container->get(My\ConfigInterface::class); // and
$container->get('db'); // etc
```

### PSR-11 Compliant

Means it has `get($serviceId)` and `has($serviceId)` method as required by [PSR-11 Standard](https://www.php-fig.org/psr/psr-11/)

## API

### `set($abstract, $concrete)`

Register a service(s)

| Parameters | Type | Description |
| --- | --- | --- |
| `$abstract` | `string` | Name of the service |
| `$concrete` | `callable`, `object` | Instance of the service |

You have few ways registering your services to the container. Example above you can use `Closure`, you also has the options to:

1. Use any [`callable`](https://www.php.net/manual/en/language.types.callable.php)

    ```php
    // callable string of a function name
    $container->set('myService', 'aFunctionName');

    // callable string of a static method of a class
    $container->set('myService', 'MyServiceProvider::staticMethodName');

    // callable array as a method of class instance
    $container->set('myService', [$myObj, 'methodName']);

    // callable array as a static method of class
    $container->set('myService', [MyServiceProvider::class, 'staticMethodName']);
    ```

2. Use object of class instance

    ```php
    $container->set('myService', new MyService);
    ```

3. Use string of a class name

    ```php
    // callable string of a class name
    $container->set('myService', MyServiceProvider::class);
    ```

    By registering a service as class name you have the option to resolve and inject the dependencies either for its `__construct()` and `__invoke()` method, if any. See [#2](https://github.com/projek-xyz/container/pull/2)

    ```php
    class FooBarProvider {
        protected $foo

        /**
         * The constructor dependency will be injected and the class will be initiated on register `set()`.
         * 
         * @param Foo $foo
         */
        public function __construct(Foo $foo) {
            $this->foo = $foo;
        }

        /**
         * The dependency will be injected on retrieval `get()`
         * and the returns value will be available for the `serviceId` instead of the class instance.
         * 
         * @param Bar $bar
         * @return FooBar
         */
        public function __invoke(Bar $bar) {
            return $this->foo->bar($bar);
        }
    }

    /**
     * So when you register a service like this.
     */
    $container->set(FooBarInterface::class, FooBarProvider::class);

    /**
     * You'll get instance of `FooBar` instead of `FooBarProvider`
     */
    $fooBar = $container->get(FooBarInterface::class);
    ```

### `unset($abstract)`

Unregister a service

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

### `make($callable[, $arguments|$condition[, $condition]])`

Create an instance of class without register it to the container stack.

| Parameters | Type | Description |
| --- | --- | --- |
| `$callable` | `string`, `callable` | `string` of class name or `callable` |
| `$arguments` | `array`, `\Closure` | **Optional**: pass an array to callback handler or conditionally resolve the callback |
| `$condition` | `\Closure` | **Optional**: conditionally resolve the callback |

**Usage**

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

**Notes:**
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

### Flexibilities

In-case you like the way to accessing a service instance using array, yes you can by registering `ArrayContainer` as a service

```php
use Projek\Container\ArrayContainer;

$container->set(ArrayContainer::class, ArrayContainer::class);

$container->set('myService', function (ArrayContainer $container) {
    return new MyService(
        $container['db'],
        $container[Psr\Log\LoggerInterface::class]
    );
});
```

Same thing when you want access it as a property:

```php
use Projek\Container\PropertyContainer;

$container->set(PropertyContainer::class, PropertyContainer::class);

$container->set('myService', function (PropertyContainer $container) {
    return new MyService(
        $container->db,
        $container->{Psr\Log\LoggerInterface::class} // Not convenient indeed, but yes you could 😅
    );
});
```

## License

This library is open-sourced software licensed under [MIT license](LICENSE.md).
