<?php

declare(strict_types=1);

use Projek\Container;

describe(Container\Resolver::class, function () {
    given('dummy', function () {
        return new Stubs\Dummy;
    });

    beforeEach(function () {
        $c = new Container([
            'dummy' => $this->dummy,
            'std' => stdClass::class,
            Stubs\AbstractFoo::class => Stubs\ConcreteBar::class,
        ]);

        $this->r = new Container\Resolver($c);
    });

    context('::handle', function () {
        it('should handle array of class instance & method pair', function () {
            expect($this->r->handle([$this->dummy, 'lorem']))->toEqual('dummy lorem');
        });

        it('should handle array of class name & static method pair', function () {
            expect(
                $this->r->handle([Stubs\SomeClass::class, 'staticMethod'])
            )->toBe('value from static method');
        });

        it('should not handle array of class name & non-static method pair', function () {
            expect(function () {
                $this->r->handle([Stubs\SomeClass::class, 'nonStaticMethod']);
            })->toThrow(new Container\Exception(
                'Non-static method Stubs\\SomeClass::nonStaticMethod should not be called statically'
            ));
        });

        it('should handle array of class instance & static method pair', function () {
            expect(
                $this->r->handle([new Stubs\SomeClass, 'staticMethod'])
            )->toBe('value from static method');
        });

        it('should handle array of class instance & non-static method pair', function () {
            expect(
                $this->r->handle([new Stubs\SomeClass, 'nonStaticMethod'])
            )->toBe('value from non-static method');
        });

        it('should handle string of class name & static method pair', function () {
            expect(
                $this->r->handle('Stubs\SomeClass::staticMethod')
            )->toBe('value from static method');
        });

        it('should not handle string of class name & static method pair', function () {
            expect(function () {
                $this->r->handle('Stubs\SomeClass::nonStaticMethod');
            })->toThrow(new Container\Exception(
                'Non-static method Stubs\\SomeClass::nonStaticMethod should not be called statically'
            ));
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

    context('::resolve', function () {
        it('should resolve array of class instance & method pair', function () {
            expect(
                $callable = $this->r->resolve([$this->dummy, 'lorem'])
            )->toEqual([$this->dummy, 'lorem']);

            expect(is_callable($callable))->toBeTruthy();
        });

        it('should resolve array of class name & static method pair', function () {
            $instance = new Stubs\SomeClass;

            expect(
                $callable = $this->r->resolve([$instance, 'staticMethod'])
            )->toBe([$instance, 'staticMethod']);

            expect(is_callable($callable))->toBeTruthy();
        });

        it('should resolve array of class name & non-static method pair', function () {
            $instance = new Stubs\SomeClass;

            expect(
                $callable = $this->r->resolve([$instance, 'nonStaticMethod'])
            )->toBe([$instance, 'nonStaticMethod']);

            expect(is_callable($callable))->toBeTruthy();
        });

        it('should resolve array of class name & static method pair', function () {
            expect(
                $callable = $this->r->resolve([Stubs\SomeClass::class, 'staticMethod'])
            )->toEqual([new Stubs\SomeClass, 'staticMethod']);

            expect(is_callable($callable))->toBeTruthy();
        });

        it('should resolve array of class name & non-static method pair', function () {
            expect(
                $callable = $this->r->resolve([Stubs\SomeClass::class, 'nonStaticMethod'])
            )->toEqual([new Stubs\SomeClass, 'nonStaticMethod']);

            expect(is_callable($callable))->toBeTruthy();
        });

        it('should resolve string of class name & static method pair', function () {
            expect(
                $callable = $this->r->resolve('Stubs\SomeClass::staticMethod')
            )->toEqual([new Stubs\SomeClass, 'staticMethod']);

            expect(is_callable($callable))->toBeTruthy();
        });

        it('should resolve string of class name & non-static method pair', function () {
            expect(
                $callable = $this->r->resolve('Stubs\SomeClass::nonStaticMethod')
            )->toEqual([new Stubs\SomeClass, 'nonStaticMethod']);

            expect(is_callable($callable))->toBeTruthy();
        });

        it('should resolve string of function name', function () {
            expect(
                $callable = $this->r->resolve('Stubs\dummyLorem')
            )->toBe('Stubs\dummyLorem');

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
