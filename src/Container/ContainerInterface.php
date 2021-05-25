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
     * @param mixed $entry
     * @return static
     */
    public function set(string $id, $entry): ContainerInterface;

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
     * $container->make(SomeClass::class, ['a value'])
     *
     * // Treat 2nd parameter as condition
     * $container->make(SomeClass::class, function ($instance) {
     *     // Accepts falsy or $instance of the class
     *     return $instance instanceof CertainInterface ? [$instance, 'theMethod'] : null;
     * })
     *
     * // Treat 2nd parameter as arguments and 3rd as condition
     * $container->make(SomeClass::class, ['a value'], function ($instance) {
     *     // a condition
     * })
     * ```
     *
     * @link https://github.com/projek-xyz/container/pull/12
     * @param string|callable $entry String of class name or callable
     * @param null|array|\Closure ...$args
     * @return mixed
     */
    public function make($entry, ...$args);
}
