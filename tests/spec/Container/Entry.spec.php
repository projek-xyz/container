<?php

declare(strict_types=1);

use Projek\Callable\Handler;
use Projek\Callable\Resolver;
use Projek\Container\Entry;
use Projek\Container\EntryCollector;
use Projek\Container\ResolutionException;
use Psr\Container\ContainerInterface;
use Stubs\AbstractFoo;
use Stubs\ConcreteBar;
use Stubs\Dummy;
use Stubs\MultiParamStub;
use Stubs\StubContainer;

use function Kahlan\describe;
use function Kahlan\expect;
use function Kahlan\it;

describe(Entry::class, function () {
    $subject = function (array $parameters = [], ?string $returnType = null, bool $auto = false): Entry {
        return new class('entry-id', $parameters, $returnType, $auto) extends Entry
        {
            public mixed $produced = 'produced';

            public ?array $lastArgs = null;

            public ?Handler $lastHandler = null;

            public ?ContainerInterface $lastContainer = null;

            /**
             * Record the build's handler, container and args, then hand back the canned value.
             */
            protected function produce(Handler $handler, ContainerInterface $container, array $args): mixed
            {
                $this->lastArgs = $args;
                $this->lastHandler = $handler;
                $this->lastContainer = $container;

                return $this->produced;
            }

            /**
             * No derivable extension target — extend() must reject this entry.
             */
            public function extensionTarget(EntryCollector $entries): ?string
            {
                return null;
            }

            /**
             * Public exposure of the protected extractMetadata() helper for the metadata specs.
             */
            public static function metadata(ReflectionFunctionAbstract $reflection): array
            {
                return self::extractMetadata($reflection);
            }
        };
    };

    it('exposes identity, metadata and initial state through its accessors', function () use ($subject) {
        $entry = $subject(['dummy' => 'Stubs\Dummy'], 'Stubs\Dummy', true);

        expect($entry->id)->toBe('entry-id');
        expect($entry->parameters)->toBe(['dummy' => 'Stubs\Dummy']);
        expect($entry->returnType)->toBe('Stubs\Dummy');
        expect($entry->auto)->toBeTruthy();
        expect($entry->isBuilt())->toBeFalsy();
        expect($entry->value())->toBeNull();
    });

    it('declares the template contract as final/abstract', function () {
        expect((new ReflectionMethod(Entry::class, 'build'))->isFinal())->toBeTruthy();
        expect((new ReflectionMethod(Entry::class, 'produce'))->isAbstract())->toBeTruthy();
        expect((new ReflectionMethod(Entry::class, 'extensionTarget'))->isAbstract())->toBeTruthy();
    });

    it('builds through the produce template, forwarding handler, container and args', function () use ($subject) {
        $entry = $subject();
        $container = new StubContainer([]);
        $handler = new Handler(new Resolver(new StubContainer([])));

        expect($entry->build($handler, $container))->toBe('produced');
        /** @disregard */
        expect($entry->lastHandler)->toBe($handler);
        /** @disregard */
        expect($entry->lastContainer)->toBe($container);
        /** @disregard */
        expect($entry->lastArgs)->toBe([]);

        $entry->build($handler, $container, ['seed' => 'value']);

        /** @disregard */
        expect($entry->lastArgs)->toBe(['seed' => 'value']);
    });

    it('applies the whole decorator list from index 0 with the current value as positional argument 0', function () use ($subject) {
        $entry = $subject();
        $seen = [];
        $handler = new Handler(new Resolver(new StubContainer([])));

        $entry->decorate(function ($value) use (&$seen) {
            $seen[] = $value;

            return $value.' first';
        });
        $entry->decorate(fn (string $value): string => $value.' second');

        expect($entry->build($handler, new StubContainer([])))->toBe('produced first second');
        expect($seen)->toBe(['produced']);
    });

    it('auto-wires decorator parameters beyond the current value', function () use ($subject) {
        $entry = $subject();
        $foo = new ConcreteBar(null);
        $container = new StubContainer([AbstractFoo::class => $foo]);
        $handler = new Handler(new Resolver($container));

        $entry->decorate(function ($value, AbstractFoo $injected) use ($foo): string {
            return $value.($injected === $foo ? ' wired' : ' broken');
        });

        expect($entry->build($handler, $container))->toBe('produced wired');
    });

    it('re-decorates a fresh value completely after a mid-loop failure', function () use ($subject) {
        $entry = $subject();
        $firstRuns = 0;
        $failing = true;
        $handler = new Handler(new Resolver(new StubContainer([])));

        $entry->decorate(function ($value) use (&$firstRuns) {
            $firstRuns++;

            return $value.' first';
        });
        $entry->decorate(function ($value) use (&$failing) {
            if ($failing) {
                $failing = false;

                throw new RuntimeException('decoration failed');
            }

            return $value.' second';
        });

        expect(fn () => $entry->build($handler, new StubContainer([])))->toThrow(
            new RuntimeException('decoration failed')
        );

        expect($entry->build($handler, new StubContainer([])))->toBe('produced first second');
        expect($firstRuns)->toBe(2);
    });

    it('appends decorators without touching the cache', function () use ($subject) {
        $entry = $subject();
        $handler = new Handler(new Resolver(new StubContainer([])));

        $entry->cache('cached');
        $entry->decorate(fn (string $value): string => $value.'!');

        expect($entry->isBuilt())->toBeTruthy();
        expect($entry->value())->toBe('cached');
        expect($entry->build($handler, new StubContainer([])))->toBe('produced!');
    });

    it('caches values of any type', function () use ($subject) {
        $entry = $subject();

        $entry->cache(['a' => 'b']);

        expect($entry->isBuilt())->toBeTruthy();
        expect($entry->value())->toBe(['a' => 'b']);

        $entry->cache('scalar');

        expect($entry->value())->toBe('scalar');
    });

    it('guards against circular builds', function () use ($subject) {
        $entry = $subject();

        $entry->beginBuild();

        expect(fn () => $entry->beginBuild())->toThrow(
            new ResolutionException('Failed to resolve "entry-id": circular reference while building.')
        );

        // Error boundary: new ResolutionException($msg, $e) — message round-trips, previous is kept
        $previous = new RuntimeException('inner cause');
        $wrapped = new ResolutionException('Failed to resolve "entry-id": inner cause', $previous);

        expect($wrapped->getMessage())->toBe('Failed to resolve "entry-id": inner cause');
        expect($wrapped->getPrevious())->toBe($previous);

        $entry->endBuild();
        $entry->beginBuild();

        expect($entry->isBuilt())->toBeFalsy();

        $entry->endBuild();
        $entry->endBuild();
    });

    it('resets cache and guard state on clone while keeping metadata and decorators', function () use ($subject) {
        $entry = $subject(['dummy' => 'Stubs\Dummy'], 'Stubs\Dummy');
        $handler = new Handler(new Resolver(new StubContainer([])));

        $entry->decorate(fn (string $value): string => $value.' decorated');
        $entry->cache('stale');
        $entry->beginBuild();

        $clone = clone $entry;

        expect($clone->id)->toBe('entry-id');
        expect($clone->parameters)->toBe(['dummy' => 'Stubs\Dummy']);
        expect($clone->returnType)->toBe('Stubs\Dummy');
        expect($clone->isBuilt())->toBeFalsy();
        expect($clone->value())->toBeNull();

        $clone->beginBuild();
        $clone->endBuild();

        expect($clone->build($handler, new StubContainer([])))->toBe('produced decorated');

        $entry->endBuild();

        expect($entry->isBuilt())->toBeTruthy();
        expect($entry->value())->toBe('stale');
    });

    it('extracts parameter and return type metadata from a closure', function () use ($subject) {
        $entry = $subject();
        $closure = function (AbstractFoo $foo, ?string $text, $raw, string|int $num): string {
            return 'x';
        };

        /** @disregard */
        [$parameters, $returnType] = $entry::metadata(new ReflectionFunction($closure));

        expect($parameters)->toBe([
            'foo' => 'Stubs\AbstractFoo',
            'text' => '?string',
            'raw' => null,
            'num' => 'string|int',
        ]);
        expect($returnType)->toBe('string');
    });

    it('extracts metadata for plain functions without a scope', function () use ($subject) {
        $entry = $subject();

        // the function lives in Dummy.php — trigger the class autoload first
        \class_exists(Dummy::class);

        /** @disregard */
        [$parameters, $returnType] = $entry::metadata(new ReflectionFunction('Stubs\dummyLorem'));

        expect($parameters)->toBe(['foo' => 'Stubs\AbstractFoo']);
        expect($returnType)->toBeNull();
    });

    it('extracts method metadata against the declaring class scope', function () use ($subject) {
        $entry = $subject();

        /** @disregard */
        [$parameters, $returnType] = $entry::metadata(new ReflectionMethod(MultiParamStub::class, 'chain'));

        expect($parameters)->toBe(['next' => 'Stubs\MultiParamStub']);
        expect($returnType)->toBe('Stubs\MultiParamStub');
    });

    it('resolves self, static and parent to FQCN at extraction time', function () use ($subject) {
        $entry = $subject();
        $scope = new class extends AbstractFoo
        {
            /**
             * Return a closure declaring a `self` return type.
             */
            public function selfClosure()
            {
                /** @disregard */
                return function (): self {};
            }

            /**
             * Return a closure declaring a `static` return type.
             */
            public function staticClosure()
            {
                /** @disregard */
                return function (): static {};
            }

            /**
             * Return a closure declaring a `parent` return type.
             */
            public function parentClosure()
            {
                /** @disregard */
                return function (): parent {};
            }

            /**
             * Return a closure with a `self`-typed parameter.
             */
            public function selfParamClosure()
            {
                return function (self $me) {};
            }
        };

        /** @disregard */
        [, $self] = $entry::metadata(new ReflectionFunction($scope->selfClosure()));
        /** @disregard */
        [, $static] = $entry::metadata(new ReflectionFunction($scope->staticClosure()));
        /** @disregard */
        [, $parent] = $entry::metadata(new ReflectionFunction($scope->parentClosure()));
        /** @disregard */
        [$parameters] = $entry::metadata(new ReflectionFunction($scope->selfParamClosure()));

        expect($self)->toBe(\get_class($scope));
        expect($static)->toBe(\get_class($scope));
        expect($parent)->toBe('Stubs\AbstractFoo');
        expect($parameters)->toBe(['me' => \get_class($scope)]);
    });
});
