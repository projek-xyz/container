<?php

namespace Stubs;

interface FooInterface {
    public function fooMethod();
}

interface BarInterface {
    public function barMethod();
}

interface FooBarInterface extends FooInterface, BarInterface {}

class FooBar implements FooBarInterface {
    public function fooMethod() {
        return 'value from foo';
    }

    public function barMethod() {
        return 'value from bar';
    }
}
