This tiny library aims to provide a dead-simple PSR-11 implementation with flexible service registration.

## Requirements

- PHP 8.0+ and tested up to PHP 8.5

## Installation

Use [Composer](https://getcomposer.org/):

```bash
$ composer require projek-xyz/container --prefer-dist
```

## API

- [`Container::set()`](registering-an-instance) to register an instance
- [`Container::make()`](create-an-instance) to create an instance
- [`Container::extend()`](extending-an-instance) to extend an instance
- [`ContainerAware`](container-awareness) for auto-injecting the container

## Basic Usage

Define your `services.php` file:

```php
return [
    /**
     * Let's say you have your own config factory class, you can register it as a class instance.
     */
    My\ConfigInterface::class => My\ConfigFactory::loadFile('path/to/config.php'),

    /**
     * Then you want to configure your logger based on the configuration file you already loaded. 
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
     * You could also fetch an instance by simply referencing the container name to return its instance.
     */
    'appSettings' => function ($db) {
        $db->query('SELECT * FROM app_settings');

        return $db->fetch();
    }
];
```

Now it's time to initialize the services:

```php
$container = new Projek\Container(require 'path/to/services.php');

// Now you have access to all entries:
$container->get(My\ConfigInterface::class); // and
$container->get('db'); // etc.
```

### PSR-11 Compliant

This means it has the `get($id)` and `has($id)` methods as required by the [PSR-11 Standard](https://www.php-fig.org/psr/psr-11/).

## Exceptions

The library provides the following custom exceptions under the `Projek\Container` namespace:

- `NotFoundException`: Thrown when a service ID is not found in the container (implements `Psr\Container\NotFoundExceptionInterface`).
- `InvalidArgumentException`: Thrown when an invalid argument is passed to a container method.
- `Exception`: A general exception for container-related errors (implements `Psr\Container\ContainerExceptionInterface`).
