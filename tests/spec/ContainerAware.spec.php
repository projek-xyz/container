<?php

declare(strict_types=1);

use Projek\Container;
use Projek\Container\ContainerAware;
use Projek\Container\HasContainer;
use Psr\Container\ContainerInterface;

use function Kahlan\describe;
use function Kahlan\expect;
use function Kahlan\given;
use function Kahlan\it;

describe(ContainerAware::class, function () {
    given('container', function () {
        return new Container;
    });

    given('stub', function () {
        return new class implements ContainerAware
        {
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
