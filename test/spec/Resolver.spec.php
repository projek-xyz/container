<?php

use Projek\Container;
use Projek\Container\Resolver;
use Stubs\ { Dummy, AbstractFoo, ConcreteBar };
use function Kahlan\describe;
use function Kahlan\expect;
use function Kahlan\given;
use function Stubs\dummyLorem;

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

    it('could handle array callable', function () {
        expect(
            $this->r->handle([$this->dummy, 'lorem'])
        )->toEqual('dummy lorem');

        expect(
            $this->r->handle([ConcreteBar::class, 'std'])
        )->toBeAnInstanceOf(stdClass::class);
    });

    it('could handle string callable', function () {
        expect(
            $this->r->handle(join('::', [ConcreteBar::class, 'std']))
        )->toBeAnInstanceOf(stdClass::class);

        expect(
            $this->r->handle('Stubs\dummyLorem')
        )->toEqual('lorem');
    });

    it('could handle closure callable', function () {
        $i = $this->r->handle(function (AbstractFoo $foo, $dummy, $std) {
            expect($foo)->toBeAnInstanceOf(ConcreteBar::class);
            expect($dummy)->toBeAnInstanceOf(Dummy::class);

            return $std;
        });

        expect($i)->toBeAnInstanceOf(stdClass::class);
    });

    it('could handle unresolved parameter', function () {
        expect($this->r->handle(function ($foobar, $dummy) {
            return $foobar ?? $dummy;
        }, ['foobar']))->toEqual('foobar');

        expect($this->r->handle(function ($dummy, $foobar = null) {
            return $foobar ?? $dummy;
        }))->toBeAnInstanceOf(Dummy::class);
    });

    it('could resolve array callable', function () {
        expect(
            $this->r->resolve([$this->dummy, 'lorem'])
        )->toEqual([$this->dummy, 'lorem']);

        expect(
            $this->r->resolve([ConcreteBar::class, 'std'])
        )->toEqual([ConcreteBar::class, 'std']);
    });

    it('could resolve string callable', function () {
        expect(
            $this->r->resolve($class = join('::', [ConcreteBar::class, 'std']))
        )->toEqual($class);

        expect(
            $this->r->resolve('Stubs\dummyLorem')
        )->toEqual('Stubs\dummyLorem');
    });

    it('could resolve closure callable', function () {
        $i = $this->r->resolve(function (AbstractFoo $foo, $dummy, $std) {
            expect($foo)->toBeAnInstanceOf(ConcreteBar::class);
            expect($dummy)->toBeAnInstanceOf(Dummy::class);

            return $std;
        });

        expect($i)->toBeAnInstanceOf(\Closure::class);
    });

    it('could resolve instance of class', function () {
        expect(
            $this->r->resolve($this->dummy)
        )->toBeAnInstanceOf(Dummy::class);
    });
});
