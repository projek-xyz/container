<?php

use Projek\Container;
use Projek\Container\ContainerAware;
use Projek\Container\ContainerAwareInterface;
use Projek\Container\ContainerInterface;
use Projek\Container\Resolver;
use Stubs\ { Dummy, AbstractFoo, CloneContainer, ConcreteBar };

use function Kahlan\context;
use function Kahlan\describe;
use function Kahlan\expect;
use function Kahlan\given;

describe(Resolver::class, function () {
    given('dummy', function () {
        return new Dummy;
    });

    beforeEach(function () {
        $c = new Container([
            'dummy' => $this->dummy,
            'std' => stdClass::class,
            AbstractFoo::class => ConcreteBar::class,
        ]);

        $this->r = new Resolver($c);
    });

    context(Resolver::class.'::handle', function () {
        it('should handle array callable', function () {
            expect(
                $this->r->handle([$this->dummy, 'lorem'])
            )->toEqual('dummy lorem');

            expect(
                $this->r->handle([ConcreteBar::class, 'std'])
            )->toBeAnInstanceOf(stdClass::class);
        });

        it('should handle string callable', function () {
            expect(
                $this->r->handle(join('::', [ConcreteBar::class, 'std']))
            )->toBeAnInstanceOf(stdClass::class);

            expect(
                $this->r->handle('Stubs\dummyLorem')
            )->toEqual('lorem');
        });

        it('should handle closure callable', function () {
            $i = $this->r->handle(function (AbstractFoo $foo, $dummy, $std) {
                expect($foo)->toBeAnInstanceOf(ConcreteBar::class);
                expect($dummy)->toBeAnInstanceOf(Dummy::class);

                return $std;
            });

            expect($i)->toBeAnInstanceOf(stdClass::class);
        });

        it('should handle unresolved parameter', function () {
            expect($this->r->handle(function ($foobar, $dummy) {
                return $foobar ?? $dummy;
            }, ['foobar']))->toEqual('foobar');

            expect($this->r->handle(function ($dummy, $foobar = null) {
                return $foobar ?? $dummy;
            }))->toBeAnInstanceOf(Dummy::class);
        });
    });

    context(Resolver::class.'::resolve', function () {
        it('should resolve array callable', function () {
            expect(
                $this->r->resolve([$this->dummy, 'lorem'])
            )->toEqual([$this->dummy, 'lorem']);

            expect(
                $this->r->resolve([ConcreteBar::class, 'std'])
            )->toEqual([ConcreteBar::class, 'std']);
        });

        it('should resolve string callable', function () {
            expect(
                $this->r->resolve(join('::', [ConcreteBar::class, 'std']))
            )->toEqual([ConcreteBar::class, 'std']);

            expect(
                $this->r->resolve('Stubs\dummyLorem')
            )->toEqual('Stubs\dummyLorem');
        });

        it('should resolve closure callable', function () {
            $i = $this->r->resolve(function (AbstractFoo $foo, $dummy, $std) {
                expect($foo)->toBeAnInstanceOf(ConcreteBar::class);
                expect($dummy)->toBeAnInstanceOf(Dummy::class);

                return $std;
            });

            expect($i)->toBeAnInstanceOf(\Closure::class);
        });

        it('should resolve instance of class', function () {
            expect(
                $this->r->resolve($this->dummy)
            )->toBeAnInstanceOf(Dummy::class);
        });

        it('should resolve existing container', function () {
            expect(
                $this->r->resolve('dummy')
            )->toBe('dummy');
        });

        it('should autowire '.ContainerAwareInterface::class.' instance', function () {
            $class = new class implements ContainerAwareInterface {
                use ContainerAware;
            };

            // Assign the original container.

            /** @var ContainerInterface $container */
            $container = $this->r->resolve($class)->getContainer();
            expect($container)->toBeAnInstanceOf(ContainerInterface::class);
            expect($container->has('foobar'))->toBeFalsy();

            $container = $this->r->resolve(get_class($class))->getContainer();
            expect($container)->toBeAnInstanceOf(ContainerInterface::class);
            expect($container->has('foobar'))->toBeFalsy();

            // Modify container stack interally

            $clone = $this->r->resolve(CloneContainer::class)->getContainer();
            expect($clone)->toBeAnInstanceOf(ContainerInterface::class);
            expect($clone->has('foobar'))->toBeTruthy();
            expect($container->has('foobar'))->toBeFalsy();
        });
    });
});
