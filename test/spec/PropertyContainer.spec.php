<?php

declare(strict_types=1);

use Projek\Container;
use Projek\Container\{Exception, PropertyContainer};
use Stubs\AbstractFoo;

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

    it('should throw exception when setting incorrect param', function () {
        expect(function () {
            $this->c->foo = AbstractFoo::class;
        })->toThrow(new Exception(sprintf('Target "%s" is not instantiable.', AbstractFoo::class)));

        expect(function () {
            $this->c->foo = ['foo', 'bar'];
        })->toThrow(new Exception\UnresolvableException(['foo', 'bar']));

        expect(function () {
            $this->c->foo = null;
        })->toThrow(new Exception\UnresolvableException(null));
    });
});
