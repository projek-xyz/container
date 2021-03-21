<?php

declare(strict_types=1);

use Projek\Container;
use Projek\Container\{ContainerAware, ContainerAwareInterface, ContainerInterface, Resolver};

describe(Resolver::class, function () {
    given('dummy', function () {
        return new Stubs\Dummy;
    });

    beforeEach(function () {
        $c = new Container([
            'dummy' => $this->dummy,
            'std' => stdClass::class,
            Stubs\AbstractFoo::class => Stubs\ConcreteBar::class,
        ]);

        $this->r = new Resolver($c);
    });

    context(Resolver::class.'::handle', function () {
        it('should handle array of class instance & method pair', function () {
            expect($this->r->handle([$this->dummy, 'lorem']))->toEqual('dummy lorem');
        });

        it('should handle array of class instance & static method pair', function () {
            expect($this->r->handle([Stubs\ConcreteBar::class, 'std']))->toBeAnInstanceOf(stdClass::class);
        });

        it('should handle array of class instance & static method pair', function () {
            expect(
                $this->r->handle([Stubs\SomeClass::class, 'shouldCalled'])
            )->toEqual('a value');
        });

        it('should handle string of class name & static method pair', function () {
            expect(
                $this->r->handle('Stubs\ConcreteBar::std')
            )->toBeAnInstanceOf(stdClass::class);
        });

        it('should handle string of function name', function () {
            expect($this->r->handle('Stubs\dummyLorem'))->toEqual('lorem');
        });

        it('should handle closure callable', function () {
            expect($this->r->handle(function (Stubs\AbstractFoo $foo, $dummy, $std) {
                expect($foo)->toBeAnInstanceOf(Stubs\ConcreteBar::class);
                expect($dummy)->toBeAnInstanceOf(Stubs\Dummy::class);

                return $std;
            }))->toBeAnInstanceOf(stdClass::class);
        });

        it('should handle unresolved parameter', function () {
            expect($this->r->handle(function (string $foobar, $dummy) {
                return $foobar ?? $dummy;
            }, ['foobar']))->toBe('foobar');

            expect($this->r->handle(function ($dummy, $foobar = null) {
                return $foobar ?? $dummy;
            }))->toBeAnInstanceOf(Stubs\Dummy::class);
        });
    });

    context(Resolver::class.'::resolve', function () {
        it('should resolve array of class instance & method pair', function () {
            expect(
                $callable = $this->r->resolve([$this->dummy, 'lorem'])
            )->toEqual([$this->dummy, 'lorem']);

            expect(is_callable($callable))->toBeTruthy();
        });

        it('should resolve array of class name & static method pair', function () {
            expect(
                $callable = $this->r->resolve([Stubs\ConcreteBar::class, 'std'])
            )->toEqual([new Stubs\ConcreteBar($this->dummy), 'std']);

            expect(is_callable($callable))->toBeTruthy();
        });

        it('should resolve array of class name & non-static method pair', function () {
            expect(
                $callable = $this->r->resolve([Stubs\SomeClass::class, 'shouldCalled'])
            )->toEqual([new Stubs\SomeClass, 'shouldCalled']);

            expect(is_callable($callable))->toBeTruthy();
        });

        it('should resolve string of class name & static method pair', function () {
            expect(
                $callable = $this->r->resolve('Stubs\ConcreteBar::std')
            )->toEqual([new Stubs\ConcreteBar($this->dummy), 'std']);

            expect(is_callable($callable))->toBeTruthy();
        });

        it('should resolve string of class name & non-static method pair', function () {
            expect(
                $callable = $this->r->resolve('Stubs\SomeClass::shouldCalled')
            )->toEqual([new Stubs\SomeClass, 'shouldCalled']);

            expect(is_callable($callable))->toBeTruthy();
        });

        it('should resolve string of function name', function () {
            expect(
                $callable = $this->r->resolve('Stubs\dummyLorem')
            )->toEqual('Stubs\dummyLorem');

            expect(is_callable($callable))->toBeTruthy();
        });

        it('should resolve closure callable', function () {
            expect($this->r->resolve(function () {
                return;
            }))->toBeAnInstanceOf(\Closure::class);
        });

        it('should resolve instance of class', function () {
            expect($this->r->resolve($this->dummy))->toBeAnInstanceOf(Stubs\Dummy::class);
        });

        it('should resolve existing container', function () {
            expect($this->r->resolve('dummy'))->toBeAnInstanceOf(Stubs\Dummy::class);
        });
    });
});
