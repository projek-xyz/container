<?php

use Projek\Container;
use Projek\Container\Resolver;
use Projek\ContainerStub\ { Dummy, AbstractFoo, ConcreteBar };
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

    it('could handle array callable', function () {
        expect(
            $this->r->handle([$this->dummy, 'lorem'])
        )->toEqual('lorem');

        expect(
            $this->r->handle([ConcreteBar::class, 'std'])
        )->toBeAnInstanceOf(stdClass::class);
    });

    it('could handle string callable', function () {
        expect(
            $this->r->handle(join('::', [ConcreteBar::class, 'std']))
        )->toBeAnInstanceOf(stdClass::class);

        function dummyLorem(AbstractFoo $foo) {
            return $foo->lorem();
        }

        expect(
            $this->r->handle('dummyLorem')
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
});
