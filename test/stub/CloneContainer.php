<?php

namespace Stubs;

use Projek\Container\{ContainerAware, ContainerAwareInterface, ContainerInterface};

class CloneContainer implements ContainerAwareInterface
{
    use ContainerAware;

    public function __construct(ContainerInterface $container)
    {
        $clone = clone $container;

        $clone->set('foobar', function () {
            return 'value';
        });

        $this->setContainer($clone);
    }
}
