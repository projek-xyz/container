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