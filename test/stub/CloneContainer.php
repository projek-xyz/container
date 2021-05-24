<?php

namespace Stubs;

use Projek\Container\{HasContainer, ContainerAware, ContainerInterface};

class CloneContainer implements ContainerAware
{
    use HasContainer;

    public function __construct(ContainerInterface $container)
    {
        $clone = clone $container;

        $clone->set('foobar', function () {
            return 'value';
        });

        $this->setContainer($clone);
    }
}
