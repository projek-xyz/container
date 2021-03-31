<?php

declare(strict_types=1);

use Projek\Container;
use Projek\Container\{ArrayContainer, Exception};

describe(ArrayContainer::class, function () {
    beforeEach(function () {
        $c = new Container;
        $c->set(ArrayContainer::class, ArrayContainer::class);
        $this->c = $c->get(ArrayContainer::class);
    });

    it('could handle instance', function () {
        $this->c['std'] = new stdClass;
        expect($this->c['std'])->toBeAnInstanceOf(stdClass::class);

        unset($this->c['std']);
        expect(isset($this->c['std']))->toBeFalsy();
        expect($this->c['std'])->toBeNull();
    });

    it('should throw exception when setting incorrect param', function () {
        expect(function () {
            $this->c['foo'] = Stubs\AbstractFoo::class;
        })->toThrow(new Exception\UnresolvableException(
            new \Error('Cannot instantiate abstract class Stubs\\AbstractFoo')
        ));

        expect(function () {
            $this->c['foo'] = ['foo', 'bar'];
        })->toThrow(new Exception\UnresolvableException(['foo', 'bar']));

        expect(function () {
            $this->c['foo'] = null;
        })->toThrow(new Exception\UnresolvableException(null));
    });
});
