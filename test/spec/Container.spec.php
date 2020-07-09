<?php

use Projek\Container;
use Projek\Container\{ContainerInterface, Exception, InvalidArgumentException, NotFoundException, RangeException, UnresolvableException};
use Psr\Container\ContainerInterface as PsrContainer;
use Stubs\{Dummy, AbstractFoo, ConcreteBar, PrivateFactory, ServiceProvider, SomeClass};

use function Kahlan\beforeEach;
use function Kahlan\context;
use function Kahlan\describe;
use function Kahlan\expect;

describe(Container::class, function () {
    beforeEach(function () {
        $this->c = new Container;
    });

    context('instances', function () {
        it('Instantiable', function () {
            $m = new Container([
                stdClass::class => function() {
                    return new stdClass;
                }
            ]);

            expect($m->get(stdClass::class))->toBeAnInstanceOf(stdClass::class);
        });

        it('Should resolve it-self', function () {
            $self = [Container::class, PsrContainer::class, ContainerInterface::class];

            foreach ($self as $a) {
                foreach ($self as $b) {
                    expect($this->c->get($a))->toBeAnInstanceOf($b);
                }
            }
        });

        it('Should resolve serivce provider', function () {
            $this->c->set('dummy', Dummy::class);
            $this->c->set(AbstractFoo::class, ConcreteBar::class);

            $this->c->set('myService', ServiceProvider::class);

            expect($this->c->get('myService'))->toEqual('dummy lorem');
        });

        it('Should manage instance', function () {
            $this->c->set('dummy', Dummy::class);

            expect($this->c->has('dummy'))->toBeTruthy();
            expect($this->c->get('dummy'))->toBeAnInstanceOf(Dummy::class);

            $this->c->unset('dummy');
            expect($this->c->has('dummy'))->toBeFalsy();

            expect(function () {
                return $this->c->get('dummy');
            })->toThrow(new NotFoundException('dummy'));
        });

        it('Should not overwrite existing', function () {
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
            })->toThrow(new NotFoundException('foo'));
        });

        it('Should cache resolved instances', function () {
            $this->c->set('foo', function () {
                return 'foo';
            });
            $this->c->set('bar', function ($foo) {
                expect($foo)->toEqual('foo');

                return 'bar';
            });

            expect($this->c->get('foo'))->toEqual('foo');
        });

        it('Should be cloned with new resolver instance', function () {
            // Dependencies.
            $this->c->set('dummy', Dummy::class);

            $c = clone $this->c;
            $c->set(AbstractFoo::class, ConcreteBar::class);

            expect($this->c->has(AbstractFoo::class))->toBeFalsy();
            expect($c->has('dummy'))->toBeTruthy();
        });
    });

    context('set', function () {
        beforeEach(function () {
            $this->c->set('dummy', Dummy::class);
        });

        it('Should set an alias', function () {
            $this->c->set(AbstractFoo::class, ConcreteBar::class);
            $this->c->set('abstract', AbstractFoo::class);
            $this->c->set('foo', 'abstract');
            $this->c->set('bar', 'foo');
            $this->c->set('foobar', function ($foo, $bar, $dummy) {
                expect($foo)->toEqual($bar);

                return $dummy->lorem($foo);
            });

            $concrete = $this->c->get('abstract');
            expect($concrete)->toBeAnInstanceOf(AbstractFoo::class);
            expect($this->c->get('foo'))->toBeAnInstanceOf(AbstractFoo::class);
            expect($this->c->get('foo'))->toBe($concrete);
            expect($this->c->get('bar'))->toBeAnInstanceOf(AbstractFoo::class);
            expect($this->c->get('bar'))->toBe($concrete);
        });

        foreach ([
            'nonStaticMethod' => 'value from non-static method',
            'staticMethod' => 'value from static method',
        ] as $method => $value) {
            it('Should set a class-method pair regardless is static or non-static', function () use ($method, $value) {
                // dependency
                $this->c->set(AbstractFoo::class, ConcreteBar::class);

                $this->c->set('default', ServiceProvider::class);
                $this->c->set('asString', 'Stubs\ServiceProvider::'.$method);
                $this->c->set('asArray', [ServiceProvider::class, $method]);

                expect($this->c->get('default'))->toBe('dummy lorem');
                expect($this->c->get('asString'))->toBe($value);
                expect($this->c->get('asArray'))->toBe($value);
            });
        }

        it('shoud able to register void service', function () {
            $this->c->set('void', [SomeClass::class, 'voidMethod']);

            expect($this->c->get('void'))->toBeEmpty();
        });

        it('Should throw exception when setting incorrect param', function () {
            $notoInstantible = new Exception(sprintf('Target "%s" is not instantiable.', AbstractFoo::class));

            expect(function () {
                $this->c->make(AbstractFoo::class);
            })->toThrow($notoInstantible);

            expect(function () {
                $this->c->set('foo', AbstractFoo::class);
            })->toThrow($notoInstantible);

            expect(function () {
                $this->c->set('foo', 'NotExistsClass');
            })->toThrow(new UnresolvableException('NotExistsClass'));

            expect(function () {
                $this->c->set('foo', ['foo', 'bar']);
            })->toThrow(new UnresolvableException(['foo', 'bar']));

            expect(function () {
                $this->c->set('foo', null);
            })->toThrow(new UnresolvableException(null));
        });
    });

    context('get', function () {
        it('Should have same instance everywhere', function () {
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

    context('make', function () {
        beforeEach(function () {
            // Dependencies.
            $this->c->set('dummy', Dummy::class);
            $this->c->set(AbstractFoo::class, ConcreteBar::class);
        });

        foreach ([
            Stubs\CallableClass::class => AbstractFoo::class,
            Stubs\InstantiableClass::class => Stubs\InstantiableClass::class,
            Stubs\SomeClass::class => Stubs\SomeClass::class,
        ] as $concrete => $instance) {
            it('Should make an instance without adding to the stack', function () use ($concrete, $instance) {
                expect($this->c->has($concrete))->toBeFalsy();
                expect($this->c->make($concrete))->toBeAnInstanceOf($instance);
                expect($this->c->has($concrete))->toBeFalsy();
            });
        }

        it('Should make an instance using class name', function () {
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

        it('Should make a container name', function () {
            expect($this->c->make('dummy'))->toBeAnInstanceOf(Dummy::class);
        });

        it('shoud able to make void method', function () {
            expect($this->c->make([SomeClass::class, 'voidMethod']))->toBeEmpty();
        });

        it('Should make a function name', function () {
            function func() {
                return 'value';
            }

            expect($this->c->make('func'))->toBe('value');
        });

        it('Should make a closure', function () {
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

        foreach ([
            'a value'   => null,
            'new value' => ['new value'],
        ] as $expected => $params) {
            it('Should make a class-method pair regardless is static or non-static', function () use ($params, $expected) {
                foreach (['shouldCalled', 'staticMethod'] as $method) {
                    expect($this->c->make('Stubs\SomeClass::'.$method, $params))->toEqual($expected);
                    expect($this->c->make(['Stubs\SomeClass', $method], $params))->toEqual($expected);
                    expect($this->c->make([new SomeClass, $method], $params))->toEqual($expected);
                }
            });
        }

        it('Should make and optionally modify handler', function () {
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
            })->toThrow(new UnresolvableException([Stubs\SomeClass::class, 'notExists']));
        });

        it('Should pass second parameter as argument for the handler', function () {
            expect(
                $this->c->make(Stubs\SomeClass::class, ['new value'])
            )->toBe('new value');

            // Iggnore falsy param
            expect(
                $this->c->make(Stubs\SomeClass::class, ['new value'], null)
            )->toBe('new value');
        });

        it('Should ignore second parameter if class is not callable', function () {
            // Returns the class instance
            expect(
                $this->c->make(Stubs\InstantiableClass::class, ['new value'])
            )->toBeAnInstanceOf(Stubs\InstantiableClass::class);

            // Iggnore falsy param
            expect(
                $this->c->make(Stubs\InstantiableClass::class, ['new value'], null)
            )->toBeAnInstanceOf(Stubs\InstantiableClass::class);
        });

        it('Should throw exception if second parameter were invalid', function () {
            expect(function () {
                $this->c->make(Stubs\SomeClass::class, 'string');
            })->toThrow(new InvalidArgumentException(2, ['array', 'Closure'], 'string'));

            // Ignore falsy param
            expect(function () {
                $this->c->make(Stubs\SomeClass::class, 'string', null);
            })->toThrow(new InvalidArgumentException(2, ['array', 'Closure'], 'string'));

            expect(function () {
                // Correct condition with incorrect argument
                $this->c->make(Stubs\SomeClass::class, 'string', function ($instance) {
                    return [$instance, 'shouldCalled'];
                });
            })->toThrow(new InvalidArgumentException(2, ['array'], 'string'));
        });

        it('Should throw exception if third parameter were invalid', function () {
            expect(function () {
                $this->c->make(Stubs\SomeClass::class, ['string'], 'condition');
            })->toThrow(new InvalidArgumentException(3, ['Closure'], 'string'));
        });

        it('Should throw exception if have more than 3 parameters', function () {
            expect(function () {
                $this->c->make(Stubs\SomeClass::class, ['string'], 'condition', 'more');
            })->toThrow(new RangeException(3, 4));
        });

        it('Should ignore the optional arguments if falsy ', function () {
            expect(
                // Iggnore falsy params
                $this->c->make(Stubs\SomeClass::class, null, null)
            )->toBeAnInstanceOf(Stubs\CertainInterface::class);
        });
    });
});
