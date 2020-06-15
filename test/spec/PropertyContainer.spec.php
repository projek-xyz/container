<?php

use Projek\Container;
use Projek\Container\{ Exception, PropertyContainer };
use Stubs\AbstractFoo;
use function Kahlan\describe;
use function Kahlan\expect;

describe(PropertyContainer::class, function () {
    beforeEach(function () {
        $c = new Container;
        $c->set(PropertyContainer::class, PropertyContainer::class);
        $this->c = $c->get(PropertyContainer::class);
    });

    it('could manage instance', function () {
        $this->c->std = new stdClass;
        expect($this->c->std)->toBeAnInstanceOf(stdClass::class);

        unset($this->c->std);
        expect(isset($this->c->std))->toBeFalsy();
        expect($this->c->std)->toBeNull();
    });

    it('Should throw exception when setting incorrect param', function () {
        expect(function () {
            $this->c->foo = AbstractFoo::class;
        })->toThrow(Exception::notInstantiable(AbstractFoo::class));

        expect(function () {
            $this->c->foo = ['foo', 'bar'];
        })->toThrow(Exception::unresolvable('array'));

        expect(function () {
            $this->c->foo = null;
        })->toThrow(Exception::unresolvable('NULL'));
    });
});