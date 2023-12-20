<?php

declare(strict_types=1);

use Projek\Container;
use Psr\Container\ContainerInterface;

describe(Container::class, function () {
    beforeEach(function () {
        $this->c = new Container;
    });

    it('should resolve it-self', function () {
        $self = [Container::class, ContainerInterface::class];

        foreach ($self as $a) {
            foreach ($self as $b) {
                expect($this->c->get($a))->toBeAnInstanceOf($b);
                expect($this->c->get($a))->toBe($this->c->get($b));
            }
        }
    });

    it('should instantiable', function () {
        $m = new Container([
            stdClass::class => stdClass::class
        ]);

        expect($m->get(stdClass::class))->toBeAnInstanceOf(stdClass::class);
    });

    it('should autowire dependency if exists', function () {
        $this->c->set('dummy', Stubs\Dummy::class);
        $this->c->set(Stubs\AbstractFoo::class, Stubs\ConcreteBar::class);

        $this->c->set('a', [Stubs\SomeClass::class, 'handle']);

        expect($this->c->get('a'))->toEqual('lorem');
    });

    it('should throw an Exception if dependency not exists', function () {
        $this->c->set('a', [Stubs\SomeClass::class, 'handle']);

        expect(function () {
            $this->c->set(Stubs\AbstractFoo::class, Stubs\ConcreteBar::class);
        })->toThrow(new Container\Exception(
            'Stubs\ConcreteBar::__construct(): Argument #1 ($dummy) depends on entry "dummy" of non-exists'
        ));

        expect(function () {
            return $this->c->get('a');
        })->toThrow(new Container\Exception(
            'Stubs\SomeClass::handle(): Argument #1 ($dummy) depends on entry "Stubs\AbstractFoo" of non-exists'
        ));
    });

    it('should not overwrite existing', function () {
        $this->c->set('std', stdClass::class);
        $this->c->set('std', function () {
            return null;
        });

        expect($this->c->get('std'))->toBeAnInstanceOf(stdClass::class);
    });

    it('should cache resolved instances', function () {
        $this->c->set('foo', function () {
            return 'foo';
        });
        $this->c->set('bar', function ($foo) {
            expect($foo)->toEqual('foo');

            return 'bar';
        });

        expect($this->c->get('foo'))->toEqual('foo');
    });

    it('should handle aliases', function () {
        // Set the implementation
        $this->c->set(Stubs\FooBarInterface::class, Stubs\FooBar::class);

        // Assign alias of the implementation to the interface container
        $this->c->set(Stubs\FooInterface::class, Stubs\FooBarInterface::class);
        $this->c->set(Stubs\BarInterface::class, Stubs\FooBarInterface::class);

        $this->c->set('foobar', function (Stubs\FooInterface $foo, Stubs\BarInterface $bar) {
            expect($foo->fooMethod())->toBe('value from foo');
            expect($bar->barMethod())->toBe('value from bar');
            expect($foo)->toBe($bar);

            return 'foobar';
        });

        expect($this->c->get('foobar'))->toBe('foobar');
    });

    it('should be cloned with new resolver instance', function () {
        // Dependencies.
        $this->c->set('dummy', Stubs\Dummy::class);

        $c = clone $this->c;
        $c->set(Stubs\AbstractFoo::class, Stubs\ConcreteBar::class);

        expect($this->c->has(Stubs\AbstractFoo::class))->toBeFalsy();
        expect($c->has('dummy'))->toBeTruthy();
    });

    context('::set', function () {
        beforeEach(function () {
            $this->c->set('dummy', Stubs\Dummy::class);
        });

        it('should set an alias', function () {
            $this->c->set(Stubs\AbstractFoo::class, Stubs\ConcreteBar::class);
            $this->c->set('abstract', Stubs\AbstractFoo::class);
            $this->c->set('foo', 'abstract');
            $this->c->set('bar', 'foo');
            $this->c->set('foobar', function ($foo, $bar, $dummy) {
                expect($foo)->toEqual($bar);

                return $dummy->lorem($foo);
            });

            $concrete = $this->c->get('abstract');
            expect($concrete)->toBeAnInstanceOf(Stubs\AbstractFoo::class);
            expect($this->c->get('foo'))->toBeAnInstanceOf(Stubs\AbstractFoo::class);
            expect($this->c->get('foo'))->toBe($concrete);
            expect($this->c->get('bar'))->toBeAnInstanceOf(Stubs\AbstractFoo::class);
            expect($this->c->get('bar'))->toBe($concrete);
        });

        foreach ([
            'nonStaticMethod' => 'value from non-static method',
            'staticMethod' => 'value from static method',
        ] as $method => $value) {
            it('should set a class-method pair regardless is static or non-static', function () use ($method, $value) {
                $this->c->set('a', 'Stubs\SomeClass::'.$method);
                $this->c->set('b', [Stubs\SomeClass::class, $method]);
                $this->c->set('c', [new Stubs\SomeClass, $method]);

                expect($this->c->get('a'))->toBe($value);
                expect($this->c->get('b'))->toBe($value);
                expect($this->c->get('c'))->toBe($value);
            });

            it('should set a entry-method pair the same way as class-method pair', function () use ($method, $value) {
                $this->c->set('a', 'dummy::'.$method);
                $this->c->set('b', ['dummy', $method]);

                expect($this->c->get('a'))->toBe($value);
                expect($this->c->get('b'))->toBe($value);
            });
        }

        it('shoud able to register void service', function () {
            $this->c->set('void', [Stubs\SomeClass::class, 'voidMethod']);

            expect($this->c->get('void'))->toBeEmpty();
        });

        it('shoud able to register callable service', function () {
            // dependency of Stubs\CallableClass::__invoke method
            $this->c->set(Stubs\AbstractFoo::class, Stubs\ConcreteBar::class);

            $this->c->set('callback', new Stubs\CallableClass($this->c->get('dummy')));

            expect($this->c->get('callback'))->toBeAnInstanceOf(Stubs\AbstractFoo::class);
        });

        it('should throw TypeError when trying to depends on invalid entries', function () {
            // dependency of Stubs\CallableClass::__invoke method
            $this->c->set(Stubs\AbstractFoo::class, Stubs\ConcreteBar::class);

            // Register the callable class that has different returns type on its invoke method
            $this->c->set(Stubs\CallableClass::class, Stubs\CallableClass::class);

            $this->c->set('foobar', function (Stubs\CallableClass $cb) {
                expect($cb)->toBeAnInstanceOf(Stubs\CallableClass::class);
                return $cb;
            });

            expect(function () {
                $this->c->get('foobar');
            })->toThrow(new TypeError);
        });

        it('should throw exception when setting incorrect param', function () {
            expect(function () {
                $this->c->make(Stubs\AbstractFoo::class);
            })->toThrow(new Container\Exception('Cannot instantiate class named "Stubs\AbstractFoo"'));

            expect(function () {
                $this->c->set('foo', Stubs\AbstractFoo::class);
            })->toThrow(new Container\Exception('Cannot instantiate class named "Stubs\AbstractFoo"'));

            expect(function () {
                $this->c->set('foo', 'NotExistsClass');
            })->toThrow(new Container\Exception('Cannot resolve an entry or class named "NotExistsClass" of non-exists'));

            expect(function () {
                $this->c->set('foo', 'bar');
            })->toThrow(new Container\Exception('Cannot resolve an entry or class named "bar" of non-exists'));

            expect(function () {
                $this->c->set('foo', ['foo', 'bar']);
            })->toThrow(new Container\Exception('Cannot resolve an entry or class named "foo" of non-exists'));

            expect(function () {
                $this->c->set('foo', null);
            })->toThrow(new Container\InvalidArgumentException('Cannot resolve invalid entry of NULL'));
        });
    });

    context('::get', function () {
        it('should have same instance everywhere', function () {
            $this->c->set('foo', function () {
                return new class {
                    protected $items = [];
                    public function set($item, $value) {
                        $this->items[$item] = $value;
                    }
                    public function get($item) {
                        return $this->items[$item] ?? null;
                    }
                };
            });
            $this->c->set('bar', 'foo');

            $here = $this->c->get('foo');
            $here->set('key', 'value');

            $there = $this->c->get('foo');
            $there->set('name', 'john');

            $somewhere = $this->c->get('bar');

            foreach (['key', 'name'] as $key) {
                expect($here->get($key))->toBe($there->get($key));
                expect($there->get($key))->toBe($here->get($key));
                expect($somewhere->get($key))->toBe($here->get($key));
            }
        });
    });

    context('::make', function () {
        beforeEach(function () {
            // Dependencies.
            $this->c->set('dummy', Stubs\Dummy::class);
            $this->c->set(Stubs\AbstractFoo::class, Stubs\ConcreteBar::class);
        });

        foreach ([
            Stubs\CallableClass::class => Stubs\AbstractFoo::class,
            Stubs\InstantiableClass::class => Stubs\InstantiableClass::class,
            Stubs\SomeClass::class => Stubs\SomeClass::class,
        ] as $concrete => $instance) {
            it('should make an instance without adding to the stack', function () use ($concrete, $instance) {
                expect($this->c->has($concrete))->toBeFalsy();
                expect($this->c->make($concrete))->toBeAnInstanceOf($instance);
                expect($this->c->has($concrete))->toBeFalsy();
            });
        }

        it('should make a new instance using class name', function () {
            expect(
                $one = $this->c->make(Stubs\SomeClass::class)
            )->toBeAnInstanceOf(Stubs\CertainInterface::class);

            // Returns class instance because it's not a callable
            expect(
                $two = $this->c->make(Stubs\SomeClass::class, function () {
                    return false;
                })
            )->toBeAnInstanceOf(Stubs\CertainInterface::class);

            expect($one)->not->toBe($two);
        });

        it('should make a new instance of existing container entry', function () {
            $get = $this->c->get('dummy');

            expect(
                $make = $this->c->make('dummy')
            )->toBeAnInstanceOf(Stubs\Dummy::class);

            expect($get)->not->toBe($make);
        });

        it('shoud able to make void method', function () {
            expect($this->c->make([Stubs\SomeClass::class, 'voidMethod']))->toBeEmpty();
        });

        it('should make a function name', function () {
            function func() {
                return 'value';
            }

            expect($this->c->make('func'))->toBe('value');
        });

        it('should make a closure', function () {
            // Closure parameter passed from the second argument
            expect($this->c->make(function ($param) {
                return $param;
            }, ['value']))->toEqual('value');

            // Parameters in second argument will be passed to the handler
            // Since it's being modified from the third argument
            expect($this->c->make(function () {
                return new Stubs\SomeClass;
            }, ['value'], function ($closure) {
                $instance = $closure();

                return $instance instanceof Stubs\CertainInterface
                    ? [$instance, 'shouldCalled'] // `shouldCalled` method will get the 'value'
                    : $instance;
            }))->toEqual('value');
        });

        foreach (['nonStaticMethod', 'staticMethod'] as $method) {
            it('should make an '.$method, function () use ($method) {
                expect($this->c->make('Stubs\SomeClass::'.$method, ['value']))->toEqual('value');
                expect($this->c->make(['Stubs\SomeClass', $method], ['value']))->toEqual('value');
                expect($this->c->make([new Stubs\SomeClass, $method], ['value']))->toEqual('value');
            });
        }

        it('should make and optionally modify handler', function () {
            // Overide handler by condition
            expect(
                $this->c->make(Stubs\SomeClass::class, function ($instance) {
                    if ($instance instanceof Stubs\CertainInterface) {
                        return [$instance, 'handle'];
                    }

                    return [$instance, 'shouldCalled'];
                })
            )->toEqual('lorem');

            // Override handler and pass argument(s)
            expect(
                $this->c->make(Stubs\SomeClass::class, ['new value'], function ($instance) {
                    return [$instance, 'shouldCalled'];
                })
            )->toEqual('new value');

            expect(function () {
                $this->c->make(Stubs\SomeClass::class, function ($instance) {
                    if ($instance instanceof Stubs\CertainInterface) {
                        return [$instance, 'notExists'];
                    }

                    return null;
                });
            })->toThrow(new Container\InvalidArgumentException('Method Stubs\SomeClass::notExists() does not exist'));
        });

        it('should pass second parameter as argument for the handler', function () {
            expect(
                $this->c->make(Stubs\SomeClass::class, ['new value'])
            )->toBe('new value');

            // Iggnore falsy param
            expect(
                $this->c->make(Stubs\SomeClass::class, ['new value'], null)
            )->toBe('new value');
        });

        it('should ignore second parameter if class is not callable', function () {
            // Returns the class instance
            expect(
                $this->c->make(Stubs\InstantiableClass::class, ['new value'])
            )->toBeAnInstanceOf(Stubs\InstantiableClass::class);

            // Iggnore falsy param
            expect(
                $this->c->make(Stubs\InstantiableClass::class, ['new value'], null)
            )->toBeAnInstanceOf(Stubs\InstantiableClass::class);
        });

        it('should throw exception if second parameter were invalid', function () {
            expect(function () {
                $this->c->make(Stubs\SomeClass::class, 'string');
            })->toThrow(new Container\InvalidArgumentException(
                'Argument #2 must be an array or instance of closure, string given'
            ));

            expect(function () {
                // Correct condition with incorrect argument
                $this->c->make(Stubs\SomeClass::class, 'string', function ($instance) {
                    return [$instance, 'shouldCalled'];
                });
            })->toThrow(new Container\InvalidArgumentException(
                'Argument #2 must be an array, string given'
            ));
        });
    });

    context('::extend', function () {
        beforeEach(function () {
            $this->c->set('dummy', Stubs\Dummy::class);
            $this->c->set(Stubs\AbstractFoo::class, Stubs\ConcreteBar::class);
        });

        it('should not extends non-exists entry', function () {
            expect(function () {
                $this->c->extend('foo', function ($foo) {
                    return $foo;
                });
            })->toThrow(new Container\NotFoundException('foo'));
        });

        it('should not allowed to extend a non-object entries', function () {
            $this->c->set('cb', function () {
                return [];
            });

            expect(function () {
                $this->c->extend('cb', function ($cb) {
                    return $cb;
                });
            })->toThrow(new Container\Exception('Cannot extending a non-object or a callable entry of "cb"'));
        });

        it('should not allowed to extend a callable object entries', function () {
            $this->c->set(Stubs\CallableClass::class, function ($dummy) {
                return new Stubs\CallableClass($dummy);
            });

            expect(function () {
                $this->c->extend(Stubs\CallableClass::class, function (Stubs\CallableClass $cb) {
                    return $cb;
                });
            })->toThrow(new Container\Exception('Cannot extending a non-object or a callable entry of "Stubs\CallableClass"'));
        });

        it('should only returns the same object as existing entries', function () {
            expect(function () {
                $this->c->extend('dummy', function (Stubs\Dummy $dummy) {
                    return;
                });
            })->toThrow(new Container\Exception('Argument #2 callback must be returns of type "Stubs\Dummy"'));
        });

        it('should only extend a non-callable object entries', function () {
            $this->c->set(Stubs\CouldExtends::class, Stubs\CouldExtends::class);

            $oldEntry = $this->c->get(Stubs\CouldExtends::class);
            // Make sure one of the entry's props is correct
            expect($oldEntry->dummy)->toBe($this->c->get('dummy'));

            // The first argument passed to the extend callback is the actual object instance of the entry
            $newEntry = $this->c->extend(Stubs\CouldExtends::class, function (Stubs\CouldExtends $entry, $dummy) {
                // Make sure to retrieve any registered entries from the callback argument
                expect($entry->dummy)->toBe($dummy);

                // Assume this as extending some functionalities of the current instance
                $entry->dummy = new Stubs\Dummy;

                // Returns the new entry
                return $entry;
            });

            // It should over-write the instance
            expect($newEntry)->toBe($oldEntry);
            // Make sure it have the correct object
            expect($newEntry)->toBeAnInstanceOf(Stubs\CouldExtends::class);
            expect($oldEntry->dummy)->not->toBe($this->c->get('dummy'));
            expect($newEntry->dummy)->not->toBe($this->c->get('dummy'));
        });
    });
});
