<?php

use Projek\Container;
use Projek\Container\{ContainerAware, ContainerAwareInterface, ContainerInterface};

use function Kahlan\{describe, expect, given, it};

describe(ContainerAware::class, function () {
    given('container', function () {
        return new Container;
    });

    given('stub', function () {
        return new class implements ContainerAwareInterface {
            use ContainerAware;
        };
    });

    it('should returns null if no container assigned', function () {
        expect($this->stub->getContainer())->toBeNull();
    });

    it('should assign container', function () {
        $this->stub->setContainer($this->container);

        expect($this->stub->getContainer())->toBeAnInstanceOf(ContainerInterface::class);
    });
});
