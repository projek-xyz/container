<?php

use Projek\Container;
use Projek\Container\{ContainerInterface, Exception, NotFoundException };
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
        expect(function () {
            $this->c->make(AbstractFoo::class);
        })->toThrow(Exception::notInstantiable(AbstractFoo::class));

        expect(function () {
            $this->c->set('foo', AbstractFoo::class);
        })->toThrow(Exception::notInstantiable(AbstractFoo::class));

        expect(function () {
            $this->c->set('foo', 'NotExistsClass');
        })->toThrow(Exception::unresolvable('NotExistsClass'));

        expect(function () {
            $this->c->set('foo', ['foo', 'bar']);
        })->toThrow(Exception::unresolvable('array'));

        expect(function () {
            $this->c->set('foo', null);
        })->toThrow(Exception::unresolvable('NULL'));
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
        })->toThrow(new BadMethodCallException);
    });
});
