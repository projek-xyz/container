This tiny library aims to provide dead simple PSR-11 implementation with flexible service registration.

## Requirements

- PHP 7.2+ and tested up-to PHP 8.1

## Installation

Use [Composer](https://getcomposer.org/)

```bash
$ composer require projek-xyz/container --prefer-dist
```

## API

- [`Container::set()`](registering-an-instance) to Registering an instance
- [`Container::make()`](create-an-instance) to Create an instance
- [`Container::extend()`](extending-an-instance) to Extending an instance

## Basic Usage

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

Now time to initialize the services

```php
$container = new Projek\Container(require 'path/to/services.php');

// now you have access to all
$container->get(My\ConfigInterface::class); // and
$container->get('db'); // etc
```

### PSR-11 Compliant

Means it has `get($id)` and `has($id)` method as required by [PSR-11 Standard](https://www.php-fig.org/psr/psr-11/)
