[![Actions Status](https://github.com/projek-xyz/container/workflows/Tests/badge.svg)](https://github.com/projek-xyz/container/actions) [![Coverage Status](https://coveralls.io/repos/github/projek-xyz/container/badge.svg)](https://coveralls.io/github/projek-xyz/container) [![Maintainability](https://api.codeclimate.com/v1/badges/c2b5daae6ac7b2efcdb7/maintainability)](https://codeclimate.com/github/projek-xyz/container/maintainability)

# Simple yet Flexible PSR-11 Container Implementation

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
```
### Registering service individually

```php
/**
 * Terms $abstract & $concrete was borrowed from Laravel 🙄
 */
$container->set(string $abstract, $concrete);
```

### PSR-11 Compliant

Means it has `get($serviceId)` and `has($serviceId)` method as required by [PSR-11 Standard](https://www.php-fig.org/psr/psr-11/)

### API

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

By registering a service as class name you have the option to resolve and inject the dependencies either for its `__construct()` and `__invoke()` method, if any. See #2

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

## Flexibilities

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
