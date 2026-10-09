<?php

declare(strict_types=1);

namespace Stubs;

/**
 * Every member takes its argument by reference: used to prove the
 * registration-time by-ref rejections (constructor, __invoke) and that
 * method pairs deliberately do NOT reject by-ref methods.
 */
class ByRefStub
{
    /**
     * By-reference constructor parameter: rejected at registration time.
     */
    public function __construct(mixed &$value = null)
    {
        // .
    }

    /**
     * By-reference method parameter: method pairs deliberately do NOT reject this shape.
     */
    public function byRefMethod(mixed &$value): void
    {
        $value = 'by-ref method called';
    }

    /**
     * By-reference __invoke parameter: rejected at registration time for callable objects.
     */
    public function __invoke(mixed &$value): void
    {
        $value = 'by-ref invoke called';
    }
}
