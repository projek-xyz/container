<?php

declare(strict_types=1);

use Projek\Container;
use Projek\Container\{HasContainer, ContainerAware};
use Psr\Container\ContainerInterface;

describe(ContainerAware::class, function () {
    given('container', function () {
        return new Container;
    });

    given('stub', function () {
        return new class implements ContainerAware {
            use HasContainer;
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
