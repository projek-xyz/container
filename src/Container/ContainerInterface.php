<?php

declare(strict_types=1);

namespace Projek\Container;

use Psr\Container\ContainerInterface as PsrContainerInterface;

interface ContainerInterface extends PsrContainerInterface
{
    /**
     * Add new instance.
     *
     * @param string $id
     * @param mixed $instance
     * @return void
     */
    public function set(string $id, $instance): void;

    /**
     * Unset instance.
     *
     * @param string $id
     * @return void
     */
    public function unset(string $id): void;

    /**
     * Resolve an instance without adding it to the stack.
     *
     * It's possible to add 2nd parameter as an array and it will pass it to
     * `Resolver::handle($instance, $args)`. While if it was a Closure, it will
     * treaten as condition.
     *
     * ```php
     * // Treat 2nd parameter as arguments
     * $container->make(SomeClass::class, ['foo', 'bar'])
     *
     * $container->make(SomeClass::class, function ($instance) {
     *     // a condition
     * })
     *
     * // Treat 2nd parameter as arguments and 3rd as condition
     * $container->make(SomeClass::class, ['foo', 'bar'], function ($instance) {
     *     // a condition
     * })
     * ```
     *
     * @param string $concrete
     * @param null|array|\Closure ...$args
     * @return mixed
     */
    public function make(string $concrete, ...$args);
}
