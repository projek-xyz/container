<?php

declare(strict_types=1);

use Projek\Container;
use Projek\Container\{Exception, PropertyContainer};

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
            $this->c->foo = Stubs\AbstractFoo::class;
        })->toThrow(new Exception\UnresolvableException(
            new \Error('Cannot instantiate abstract class Stubs\\AbstractFoo')
        ));

        expect(function () {
            $this->c->foo = ['foo', 'bar'];
        })->toThrow(new Exception\UnresolvableException(
            new \ReflectionException('Class foo does not exist')
        ));

        expect(function () {
            $this->c->foo = null;
        })->toThrow(new Exception\UnresolvableException(null));
    });
});
