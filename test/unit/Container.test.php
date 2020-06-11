<?php

use Projek\Container;
use Projek\Container\{ArrayContainer, ContainerInterface, Exception, NotFoundException, Resolver };
use Projek\ContainerStub\ { Dummy, AbstractFoo, ConcreteBar };
use Psr\Container\ContainerInterface as PsrContainer;
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

        expect($m)->toBeAnInstanceOf(ContainerInterface::class);
        expect($m)->toBeAnInstanceOf(PsrContainer::class);
        expect($m->get(stdClass::class))->toBeAnInstanceOf(stdClass::class);
    });

    it('Should register it-self', function () {
        expect($this->c->get(PsrContainer::class))->toBeAnInstanceOf(PsrContainer::class);
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

    it('Should resolve instance', function () {
        $this->c->set('dummy', function () {
            return new Dummy;
        });
        expect($this->c->get('dummy'))->toBeAnInstanceOf(Dummy::class);

        $this->c->set(AbstractFoo::class, ConcreteBar::class);
        expect($abs = $this->c->get(AbstractFoo::class))->toBeAnInstanceOf(ConcreteBar::class);
        expect($abs->dummy)->toBeAnInstanceOf(Dummy::class);

        $this->c->set(ArrayContainer::class, ArrayContainer::class);
        $this->c->set('foo', function (ArrayContainer $c, $bar = null) {
            expect($c['notexists'])->toBeNull();
            expect(isset($c['dummy']))->toBeTruthy();
            expect($c['dummy'])->toBeAnInstanceOf(Dummy::class);
            unset($c['dummy']);

            $c['std'] = $bar ?? new stdClass;
            $c['lorem'] = [
                $c[AbstractFoo::class],
                'lorem'
            ];

            return $c[AbstractFoo::class];
        });

        expect($this->c->get('foo'))->toBeAnInstanceOf(ConcreteBar::class);
        expect($this->c->get('std'))->toBeAnInstanceOf(stdClass::class);
        expect($this->c->has('dummy'))->toBeFalsy();
        expect($this->c->has('lorem'))->toEqual('lorem');
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

    it('Should invoke callable function', function () {
        $c = new ConcreteBar(stdClass::class);
        $this->c->set('std', $c->dummy);
        $r = new Resolver($this->c);

        expect($r->handle([$c, 'std']))->toBeAnInstanceOf(stdClass::class);
    });

    it('Should throw exception when setting incorrect param', function () {
        expect(function () {
            $this->c->set('foo', AbstractFoo::class);
        })->toThrow(Exception::notInstantiable(AbstractFoo::class));

        expect(function () {
            $this->c->set('foo', ['foo', 'bar']);
        })->toThrow(Exception::unresolvable('array'));

        expect(function () {
            $this->c->set('foo', null);
        })->toThrow(Exception::unresolvable('NULL'));
    });
});
