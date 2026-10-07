<?php

declare(strict_types=1);

use Projek\Container;
use Stubs\AbstractFoo;
use Stubs\ConcreteBar;
use Stubs\Dummy;
use Stubs\SomeClass;

use function Kahlan\beforeEach;
use function Kahlan\context;
use function Kahlan\describe;
use function Kahlan\expect;
use function Kahlan\given;
use function Kahlan\it;

describe(Container\Resolver::class, function () {
    given('dummy', function () {
        return new Dummy;
    });

    beforeEach(function () {
        $c = new Container([
            'dummy' => $this->dummy,
            'std' => stdClass::class,
            AbstractFoo::class => ConcreteBar::class,
        ]);

        $this->r = new Container\Resolver($c);
    });

    context('::resolve', function () {
        it('should resolve array of class instance & method pair', function () {
            expect(
                $this->r->resolve([$this->dummy, 'lorem'])
            )->toEqual([$this->dummy, 'lorem']);
        });

        it('should resolve array of class name & static method pair', function () {
            $instance = new SomeClass;

            expect(
                $this->r->resolve([$instance, 'staticMethod'])
            )->toBe([$instance, 'staticMethod']);
        });

        it('should resolve array of class name & non-static method pair', function () {
            $instance = new SomeClass;

            expect(
                $this->r->resolve([$instance, 'nonStaticMethod'])
            )->toBe([$instance, 'nonStaticMethod']);
        });

        it('should resolve array of class name & static method pair', function () {
            expect(
                $this->r->resolve([SomeClass::class, 'staticMethod'])
            )->toEqual([new SomeClass, 'staticMethod']);
        });

        it('should resolve array of class name & non-static method pair', function () {
            expect(
                $this->r->resolve([SomeClass::class, 'nonStaticMethod'])
            )->toEqual([new SomeClass, 'nonStaticMethod']);
        });

        it('should throw error when entry is an empty array', function () {
            expect(function () {
                $this->r->resolve([]);
            })->toThrow(new Container\InvalidArgumentException(
                'Cannot resolve invalid entry of "array"'
            ));
        });

        it('should throw error when entry is an invalid array of existing class with no method', function () {
            expect(function () {
                $this->r->resolve(['Stubs\SomeClass']);
            })->toThrow(new Container\InvalidArgumentException(
                'Cannot resolve invalid entry of "array"'
            ));
        });

        it('should throw error when entry is an invalid array of non-existing class with no method', function () {
            expect(function () {
                $this->r->resolve(['ClassNotExists', 'noMethod']);
            })->toThrow(new Container\Exception(
                'Cannot resolve an entry or class named "ClassNotExists" of non-exists'
            ));
        });

        it('should resolve string of class name & static method pair', function () {
            expect(
                $this->r->resolve('Stubs\SomeClass::staticMethod')
            )->toEqual([new SomeClass, 'staticMethod']);
        });

        it('should resolve string of class name & non-static method pair', function () {
            expect(
                $this->r->resolve('Stubs\SomeClass::nonStaticMethod')
            )->toEqual([new SomeClass, 'nonStaticMethod']);
        });

        it('should throw error when entry is an invalid string of non-existing class', function () {
            expect(function () {
                $this->r->resolve('ClassNotExists::noMethod');
            })->toThrow(new Container\Exception(
                'Cannot resolve an entry or class named "ClassNotExists" of non-exists'
            ));
        });

        it('should throw error when entry is an invalid string of existing class with no method', function () {
            expect(function () {
                $this->r->resolve('Stubs\SomeClass::');
            })->toThrow(new Container\InvalidArgumentException(
                'Cannot resolve invalid entry of "array"'
            ));
        });

        it('should resolve string of function name', function () {
            expect(
                $this->r->resolve('Stubs\dummyLorem')
            )->toBe('Stubs\dummyLorem');
        });

        it('should throw error when entry is an string of non-existing function', function () {
            expect(function () {
                $this->r->resolve('non_existing_func');
            })->toThrow(new Container\Exception(
                'Cannot resolve an entry or class named "non_existing_func" of non-exists'
            ));
        });

        it('should resolve closure callable', function () {
            expect($this->r->resolve(function () {}))->toBeAnInstanceOf(Closure::class);
        });

        it('should resolve instance of class', function () {
            expect($this->r->resolve($this->dummy))->toBeAnInstanceOf(Dummy::class);
        });

        it('should resolve existing container', function () {
            expect($this->r->resolve('dummy'))->toBeAnInstanceOf(Dummy::class);
        });
    });

    context('::handle', function () {
        it('should handle array of class instance & method pair', function () {
            expect($this->r->handle([$this->dummy, 'lorem']))->toEqual('dummy lorem');
        });

        it('should handle array of class name & static method pair', function () {
            expect(
                $this->r->handle([SomeClass::class, 'staticMethod'])
            )->toBe('value from static method');
        });

        it('should not handle array of class name & non-static method pair', function () {
            expect(function () {
                $this->r->handle([SomeClass::class, 'nonStaticMethod']);
            })->toThrow(new Container\Exception(
                'Non-static method Stubs\SomeClass::nonStaticMethod should not be called statically'
            ));
        });

        it('should handle array of class instance & static method pair', function () {
            expect(
                $this->r->handle([new SomeClass, 'staticMethod'])
            )->toBe('value from static method');
        });

        it('should handle array of class instance & non-static method pair', function () {
            expect(
                $this->r->handle([new SomeClass, 'nonStaticMethod'])
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
                'Non-static method Stubs\SomeClass::nonStaticMethod should not be called statically'
            ));
        });

        it('should handle string of function name', function () {
            expect($this->r->handle('Stubs\dummyLorem'))->toEqual('lorem');
        });

        it('should handle closure callable', function () {
            expect($this->r->handle(function (AbstractFoo $foo, $dummy, $std) {
                expect($foo)->toBeAnInstanceOf(ConcreteBar::class);
                expect($dummy)->toBeAnInstanceOf(Dummy::class);

                return $std;
            }))->toBeAnInstanceOf(stdClass::class);
        });

        it('should handle unresolved parameter', function () {
            expect($this->r->handle(function (string $foobar, $dummy) {
                return $foobar ?? $dummy;
            }, ['foobar']))->toBe('foobar');

            expect($this->r->handle(function ($dummy, $foobar = null) {
                return $foobar ?? $dummy;
            }))->toBeAnInstanceOf(Dummy::class);
        });
    });
});
