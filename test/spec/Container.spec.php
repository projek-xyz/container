<?php

use Projek\Container;
use Projek\Container\{BadMethodCallException, ContainerInterface, Exception, InvalidArgumentException, NotFoundException, RangeException, UnresolvableException};
use Psr\Container\ContainerInterface as PsrContainer;
use Stubs\{Dummy, AbstractFoo, ConcreteBar, ServiceProvider};
use function Kahlan\describe;
use function Kahlan\expect;

describe(Container::class, function () {
    beforeEach(function () {
        $this->c = new Container;
    });

    it('Instantiable', function () {
        $m = new Container([
            stdClass::class => function() {
                return new stdClass;
            }
        ]);

        expect($m->get(stdClass::class))->toBeAnInstanceOf(stdClass::class);
    });

    it('Should register it-self', function () {
        $self = [Container::class, PsrContainer::class, ContainerInterface::class];

        foreach ($self as $a) {
            foreach ($self as $b) {
                expect($this->c->get($a))->toBeAnInstanceOf($b);
            }
        }
    });

    it('Should resolve serive provider', function () {
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

    it('Should set an alias', function () {
        $this->c->set('dummy', Dummy::class);
        $this->c->set(AbstractFoo::class, ConcreteBar::class);
        $this->c->set('abstract', AbstractFoo::class);
        $this->c->set('foo', 'abstract');
        $this->c->set('bar', 'foo');
        $this->c->set('foobar', function ($foo, $bar, $dummy) {
            expect($foo)->toEqual($bar);

            return $dummy->lorem($foo);
        });

        expect($this->c->get('abstract'))->toBeAnInstanceOf(AbstractFoo::class);
        expect($this->c->get('foo'))->toBeAnInstanceOf(AbstractFoo::class);
        expect($this->c->get('bar'))->toBeAnInstanceOf(AbstractFoo::class);
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

    it('Should make an instance without adding to the stack', function () {
        // Dependencies.
        $this->c->set('dummy', Dummy::class);
        $this->c->set(AbstractFoo::class, ConcreteBar::class);

        $instances = [
            Stubs\CallableClass::class => AbstractFoo::class,
            Stubs\InstantiableClass::class => Stubs\InstantiableClass::class,
            Stubs\SomeClass::class => Stubs\SomeClass::class,
        ];

        foreach ($instances as $concrete => $instance) {
            expect($this->c->has($concrete))->toBeFalsy();
            expect($this->c->make($concrete))->toBeAnInstanceOf($instance);
            expect($this->c->has($concrete))->toBeFalsy();
        }
    });

    it('Should make an instance without adding to the stack', function () {
        // Dependencies.
        $this->c->set('dummy', Dummy::class);
        $this->c->set(AbstractFoo::class, ConcreteBar::class);

        expect(
            $this->c->make(Stubs\SomeClass::class)
        )->toBeAnInstanceOf(Stubs\CertainInterface::class);

        expect(
            $this->c->make(Stubs\SomeClass::class, function ($instance) {
                if ($instance instanceof Stubs\CertainInterface) {
                    return [$instance, 'handle'];
                }

                return null;
            })
        )->toEqual('lorem');

        expect(function () {
            $this->c->make(Stubs\SomeClass::class, function ($instance) {
                if ($instance instanceof Stubs\CertainInterface) {
                    return [$instance, 'notExists'];
                }

                return null;
            });
        })->toThrow(new UnresolvableException([Stubs\SomeClass::class, 'notExists']));
    });

    it('Should accept addtional params', function () {
        expect(
            $this->c->make(Stubs\SomeClass::class, function ($instance) {
                return [$instance, 'shouldCalled'];
            })
        )->toEqual('a value');

        expect(function () {
            $this->c->make(Stubs\SomeClass::class, 'string');
        })->toThrow(new InvalidArgumentException(2, ['array', 'Closure'], 'string'));

        expect(function () {
            // only asserting 2nd param if 3rd one is falsy
            $this->c->make(Stubs\SomeClass::class, 'string', null);
        })->toThrow(new InvalidArgumentException(2, ['array', 'Closure'], 'string'));

        expect(function () {
            $this->c->make(Stubs\SomeClass::class, ['string'], 'condition');
        })->toThrow(new InvalidArgumentException(3, ['Closure'], 'string'));

        expect(function () {
            $this->c->make(Stubs\SomeClass::class, ['string'], 'condition', 'more');
        })->toThrow(new RangeException(3, 4));

        expect(function () {
            // Correct condition with incorrect argument
            $this->c->make(Stubs\SomeClass::class, 'string', function ($instance) {
                return [$instance, 'shouldCalled'];
            });
        })->toThrow(new InvalidArgumentException(2, ['array'], 'string'));

        expect(
            // Correct condition and argument
            $this->c->make(Stubs\SomeClass::class, ['new value'], function ($instance) {
                return [$instance, 'shouldCalled'];
            })
        )->toEqual('new value');
    });

    it('Should ignore the value of 2nd argument if 1st argument is not callable ', function () {
        expect(
            // Returns the class instance
            $this->c->make(Stubs\SomeClass::class, ['new value'])
        )->toBeAnInstanceOf(Stubs\CertainInterface::class);

        expect(
            // Iggnore falsy param
            $this->c->make(Stubs\SomeClass::class, ['new value'], null)
        )->toBeAnInstanceOf(Stubs\CertainInterface::class);

        expect(
            // Iggnore falsy params
            $this->c->make(Stubs\SomeClass::class, null, null)
        )->toBeAnInstanceOf(Stubs\CertainInterface::class);
    });

    it('Should accept closure', function () {
        expect($this->c->make(function ($param) {
            return $param;
        }, ['value']))->toEqual('value');

        expect($this->c->make(function () {
            return new Stubs\SomeClass;
        }, ['value'], function ($closure) {
            $instance = $closure();

            return $instance instanceof Stubs\CertainInterface
                ? [$instance, 'shouldCalled'] // `shouldCalled` method will get the 'value'
                : $closure;
        }))->toEqual('value');
    });

    it('Should returns default if condition is falsy', function () {
        expect(
            // Returns class instance because it's not a callable
            $this->c->make(Stubs\SomeClass::class, function () {
                return false;
            })
        )->toBeAnInstanceOf(Stubs\CertainInterface::class);

        expect(
            // Return value of the callable method if condition returns falsy
            $this->c->make('Stubs\SomeClass::shouldCalled', function () {
                return null; // Should accepts null, 0, '', false
            })
        )->toEqual('a value');

        expect(
            // Return value of the callable method if condition returns the instance it-self
            $this->c->make(['Stubs\SomeClass', 'shouldCalled'], function ($instance) {
                return $instance;
            })
        )->toEqual('a value');
    });

    it('Should be able to invoke a method directly without condition', function () {
        // required by the ConcreteBar
        $this->c->set('dummy', Dummy::class);

        expect(
            // Resolve and handle non-static method like a static method
            $this->c->make('Stubs\SomeClass::shouldCalled', ['new value'])
        )->toEqual('new value');

        expect(
            // Resolve and handle non-static method like a static method
            $this->c->make(['Stubs\SomeClass', 'shouldCalled'], ['new value'])
        )->toEqual('new value');

        expect(
            // Resolve and handle actual static mathod
            $this->c->make('Stubs\ConcreteBar::std', ['new value'])
        )->toEqual('new value');

        expect(
            // Resolve and handle actual static mathod
            $this->c->make(['Stubs\ConcreteBar', 'std'], ['new value'])
        )->toEqual('new value');

        expect(
            // Resolve and handle non-static method like a static method
            $this->c->make([new Stubs\SomeClass(), 'shouldCalled'], ['new value'])
        )->toEqual('new value');
    });

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

    it('Should be cloned with new resolver instance', function () {
        // Dependencies.
        $this->c->set('dummy', Dummy::class);

        $c = clone $this->c;
        $c->set(AbstractFoo::class, ConcreteBar::class);

        expect($this->c->has(AbstractFoo::class))->toBeFalsy();
        expect($c->has('dummy'))->toBeTruthy();
    });
});
