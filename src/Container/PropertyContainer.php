<?php

declare(strict_types=1);

namespace Projek\Container;

final class PropertyContainer implements ContainerAwareInterface
{
    use ContainerAware;

    /**
     * Create new instance.
     *
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->setContainer($container);
    }

    /**
     * @param string $name
     * @param mixed $instance
     * @return void
     */
    public function __set(string $name, $instance): void
    {
        $this->getContainer()->set($name, $instance);
    }

    /**
     * @param string $name
     * @return mixed
     */
    public function __get(string $name)
    {
        try {
            return $this->getContainer($name);
        } catch (NotFoundException $e) {
            return null;
        }
    }

    /**
     * @param string $name
     * @return bool
     */
    public function __isset(string $name): bool
    {
        return $this->getContainer()->has($name);
    }

    /**
     * @param string $name
     * @return void
     */
    public function __unset(string $name): void
    {
        $this->getContainer()->unset($name);
    }
}
