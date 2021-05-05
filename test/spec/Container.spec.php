<?php

declare(strict_types=1);

use Projek\Container;
use Projek\Container\Exception;
use Psr\Container\ContainerInterface as PsrContainer;
use Stubs\SomeClass;

describe(Container::class, function () {
    beforeEach(function () {
        $this->c = new Container;
    });

    it('should resolve it-self', function () {
        $self = [Container::class, PsrContainer::class, Container\ContainerInterface::class];

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

    it('should throw an UnresolvableException if dependency not exists', function () {
        $this->c->set('a', [Stubs\SomeClass::class, 'handle']);

        $unresolvable = function (string $name) {
            return new Exception\UnresolvableException(
                new Exception\NotFoundException($name)
            );
        };

        expect(function () {
            $this->c->set(Stubs\AbstractFoo::class, Stubs\ConcreteBar::class);
        })->toThrow($unresolvable('dummy'));

        expect(function () {
            return $this->c->get('a');
        })->toThrow($unresolvable(Stubs\AbstractFoo::class));
    });

    it('should manage instance', function () {
        $this->c->set('dummy', Stubs\Dummy::class);

        expect($this->c->has('dummy'))->toBeTruthy();
        expect($this->c->get('dummy'))->toBeAnInstanceOf(Stubs\Dummy::class);

        $this->c->unset('dummy');
        expect($this->c->has('dummy'))->toBeFalsy();

        expect(function () {
            return $this->c->get('dummy');
        })->toThrow(new Exception\NotFoundException('dummy'));
    });

    it('should not overwrite existing', function () {
        $this->c->set('std', stdClass::class);
        $this->c->set('std', function () {
            return null;
        });

        expect($this->c->get('std'))->toBeAnInstanceOf(stdClass::class);

        $this->c->set('stds', function ($foo) {
            return $foo;
        });
        expect(function () {
            return $this->c->get('stds');
        })->toThrow(new Exception\UnresolvableException(
            new Exception\NotFoundException('foo')
        ));
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

    context(Container::class.'::set', function () {
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
                // dependency

                $this->c->set('a', 'Stubs\SomeClass::'.$method);
                $this->c->set('b', [Stubs\SomeClass::class, $method]);
                $this->c->set('c', [new Stubs\SomeClass, $method]);

                expect($this->c->get('a'))->toBe($value);
                expect($this->c->get('b'))->toBe($value);
                expect($this->c->get('c'))->toBe($value);
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

        it('should throw exception when setting incorrect param', function () {
            $refException = function (string $class) {
                return new \ReflectionException(sprintf('Class %s does not exist', $class));
            };

            expect(function () {
                $this->c->make(Stubs\AbstractFoo::class);
            })->toThrow(new Exception\UnresolvableException(
                new \Error('Cannot instantiate abstract class Stubs\\AbstractFoo')
            ));

            expect(function () {
                $this->c->set('foo', Stubs\AbstractFoo::class);
            })->toThrow(new Exception\UnresolvableException(
                new \Error('Cannot instantiate abstract class Stubs\\AbstractFoo')
            ));

            expect(function () {
                $this->c->set('foo', 'NotExistsClass');
            })->toThrow(new Exception\UnresolvableException($refException('NotExistsClass')));
            expect(function () {
                $this->c->set('foo', 'bar');
            })->toThrow(new Exception\UnresolvableException($refException('bar')));

            expect(function () {
                $this->c->set('foo', ['foo', 'bar']);
            })->toThrow(new Exception\UnresolvableException(
                new \ReflectionException('Class foo does not exist')
            ));

            expect(function () {
                $this->c->set('foo', null);
            })->toThrow(new Exception\UnresolvableException(null));
        });
    });

    context(Container::class.'::get', function () {
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

    context(Container::class.'::make', function () {
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

        it('should make an instance using class name', function () {
            expect(
                $this->c->make(Stubs\SomeClass::class)
            )->toBeAnInstanceOf(Stubs\CertainInterface::class);

            // Returns class instance because it's not a callable
            expect(
                $this->c->make(Stubs\SomeClass::class, function () {
                    return false;
                })
            )->toBeAnInstanceOf(Stubs\CertainInterface::class);
        });

        it('should make a container name', function () {
            expect($this->c->make('dummy'))->toBeAnInstanceOf(Stubs\Dummy::class);
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
            })->toThrow(new Exception\UnresolvableException([Stubs\SomeClass::class, 'notExists']));
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
            })->toThrow(new Exception\InvalidArgumentException(2, ['array', 'Closure'], 'string'));

            // Ignore falsy param
            expect(function () {
                $this->c->make(Stubs\SomeClass::class, 'string', null);
            })->toThrow(new Exception\InvalidArgumentException(2, ['array', 'Closure'], 'string'));

            expect(function () {
                // Correct condition with incorrect argument
                $this->c->make(Stubs\SomeClass::class, 'string', function ($instance) {
                    return [$instance, 'shouldCalled'];
                });
            })->toThrow(new Exception\InvalidArgumentException(2, ['array'], 'string'));
        });

        it('should throw exception if third parameter were invalid', function () {
            expect(function () {
                $this->c->make(Stubs\SomeClass::class, ['string'], 'condition');
            })->toThrow(new Exception\InvalidArgumentException(3, ['Closure'], 'string'));
        });

        it('should throw exception if have more than 3 parameters', function () {
            expect(function () {
                $this->c->make(Stubs\SomeClass::class, ['string'], 'condition', 'more');
            })->toThrow(new Exception\RangeException(3, 4));
        });

        it('should ignore the optional arguments if falsy ', function () {
            expect(
                // Iggnore falsy params
                $this->c->make(Stubs\SomeClass::class, null, null)
            )->toBeAnInstanceOf(Stubs\CertainInterface::class);
        });
    });
});
