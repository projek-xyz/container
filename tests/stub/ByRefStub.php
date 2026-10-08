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
    public function __construct(mixed &$value = null)
    {
        // .
    }

    public function byRefMethod(mixed &$value): void
    {
        $value = 'by-ref method called';
    }

    public function __invoke(mixed &$value): void
    {
        $value = 'by-ref invoke called';
    }
}
