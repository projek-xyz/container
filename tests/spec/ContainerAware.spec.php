<?php

declare(strict_types=1);

use Projek\Container;
use Projek\Container\ContainerAware;
use Projek\Container\Events;
use Projek\Container\HasContainer;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\ListenerProviderInterface;
use Stubs\HasContainerClass;
use Stubs\RecordingDispatcher;
use Stubs\TheDispatcher;

use function Kahlan\context;
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

    context('injection', function () {
        it('should inject via EntryResolved when resolving through get()', function () {
            $c = new Container;
            $c->set(HasContainerClass::class, HasContainerClass::class);

            $instance = $c->get(HasContainerClass::class);

            expect($instance->getContainer())->toBeAnInstanceOf(Container::class);
            expect($instance->getContainer())->toBe($c);
        });

        it('should inject directly — without events — when resolving through make()', function () {
            $provider = new Events\ListenerProvider;
            $recorder = new RecordingDispatcher($provider);
            $c = new Container([], $recorder);
            $provider->setContainer($c);

            $instance = $c->make(HasContainerClass::class);

            expect($instance->getContainer())->toBe($c);
            expect($recorder->eventsFor(Events\EntryResolved::class))->toHaveLength(0);
        });

        it('should skip injection when the instance already carries a container', function () {
            $other = new Container;
            $preInjected = new class implements ContainerAware
            {
                use HasContainer;
            };
            $preInjected->setContainer($other);

            $c = new Container;
            $c->set('pre-injected', fn () => $preInjected);
            $c->get('pre-injected');

            expect($preInjected->getContainer())->toBe($other);
        });

        it('should stop injecting through a custom dispatcher that does not wire the ListenerProvider', function () {
            $emptyProvider = new class implements ListenerProviderInterface
            {
                public function getListenersForEvent(object $event): iterable
                {
                    return [];
                }
            };

            $c = new Container;
            $c->setEventDispatcher(new TheDispatcher($emptyProvider));
            $c->set(HasContainerClass::class, HasContainerClass::class);

            $instance = $c->get(HasContainerClass::class);

            // Documented caveat: the injection listener runs only when this
            // ListenerProvider is wired into the custom dispatcher.
            expect($instance->getContainer())->toBeNull();
        });

        it('should still inject on make() even under such a custom dispatcher', function () {
            $emptyProvider = new class implements ListenerProviderInterface
            {
                public function getListenersForEvent(object $event): iterable
                {
                    return [];
                }
            };

            $c = new Container;
            $c->setEventDispatcher(new TheDispatcher($emptyProvider));

            $instance = $c->make(HasContainerClass::class);

            expect($instance->getContainer())->toBe($c);
        });
    });
});
