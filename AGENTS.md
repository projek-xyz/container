# AGENTS: projek-xyz/container

A PHP PSR-11 dependency-injection container library. PSR-4 `Projek\` → `src/`, PHP >= 8.4, PSR-12, Kahlan specs, Conventional Commits.

## Quickstart Commands

| Action | Command |
|--------|---------|
| Run all checks (lint + test) | `composer test` |
| Format code (Pint, Laravel preset) | `composer format` |
| Lint code (Pint, Laravel preset) | `composer lint` |
| Run specs/Kahlan tests | `composer spec` |
| Install dependencies | `composer install` |

**Notes:**
- Run a single spec file: `composer spec -- --spec=tests/spec/Container.spec.php` (or `--grep='*Container*'`).

## Project Structure

```
src/                  # Source code (PSR-4: Projek\):
  Container.php
  Container/
    AbstractContainerAware.php
    ContainerAware.php
    Entry.php
    Entry/
      AliasEntry.php
      CallableEntry.php
      ClassNameEntry.php
      FactoryEntry.php
      MethodPairEntry.php
    EntryCollector.php
    EntryFactory.php
    Events/
      Dispatcher.php
      EntryRegistered.php
      EntryResolved.php
      ListenerProvider.php
    HasContainer.php
    InvalidArgumentException.php
    NotFoundException.php
    ResolutionException.php
tests/
  spec/               # Kahlan specifications dir
    Container.spec.php
    ContainerAware.spec.php
    EntryCollector.spec.php
    Container/
      Entry.spec.php
      Entry/
        AliasEntry.spec.php
        CallableEntry.spec.php
        ClassNameEntry.spec.php
        FactoryEntry.spec.php
        MethodPairEntry.spec.php
  stub/               # Test stubs and fixtures dir
  config.php          # Kahlan config (coverage, stubs dir)
```

## Coding Standards

- **Formatter**: `composer format` runs Pint with the Laravel preset
- **Linter**: `composer lint` runs Pint with the Laravel preset (`--test` mode fails on discrepancies)
- Both use the project's `composer.json` scripts
- PHP version requirement: `>=8.4` (defined in `composer.json`). Keep `src/` and `tests/` compatible with declared minimum PHP version.

## Testing

- Test framework: **Kahlan** (v6.x)
- Spec files live in `tests/spec/**/*.spec.php` and use `describe`/`it` + `expect()` syntax
- Spec files should mirrors `src/` files structures: `src/Foo.php` -> `tests/spec/Foo.spec.php`; `src/Foo/Bar.php` -> `tests/spec/Foo/Bar.spec.php`
- Stub file live in `tests/stub/` with `Stubs\` PSR-4 prefix (autoload-dev)
- Run all tests with `composer spec`
- CI runs tests on PHP 8.4–8.5 matrix (`.github/workflows/tests.yml`), local dev pins PHP 8.4 via `.tool-versions` (asdf/mise)
- The suite currently reports 100% coverage (203/203 statements) — new `src/` code needs specs to keep it there (CI coverage driver: xdebug)

## Git workflow

- **Commit messages must be Conventional Commits** (`feat:`, `fix:`, `chore:`, ...) — enforced by the `commit-msg` hook (commitlint); non-conforming messages are rejected. Skip hooks with `--no-verify` flag.
- `pre-commit` runs lint-staged, which applies `vendor/bin/pint --preset laravel` to staged `*.php` files (Pint fixes never block the commit)
- Hooks are installed by the `prepare` script on `npm install` (`simple-git-hooks`); `opencode.json`/`.opencode`/`.ai` are gitignored
- Release: `npm run release` (commit-and-tag-version); pushing a `v*.*.*` tag triggers the `publish` workflow (release + wiki sync)

## Conventions

- All php files **must** have `declare(strict_types=1)`
- Files are namespaced under `Projek\` (or `Stubs\` for test stubs, fixtures and helpers)
- Classes and functions **must be explicitly qualified**: either a `use` import or a `\` prefix — **never a bare call in a `strict_types` file** (e.g. DO `use function sprintf;` + `sprintf();`, or `\sprintf();`; DON'T call `sprintf();` unqualified)
- Documentation available in `.github/wiki/` is synced to the GitHub wiki by the `wiki` job in `.github/workflows/publish.yml` when a `v*.*.*` tag is pushed.
- Sample PHPMD config is available in `tests/phpmd.xml`;

## Container behaviors that bite (verified in code)

- **`set()` is lazy and strict**: classification + storage only — no construction, no dependency resolution, no `ContainerAware` injection at registration. A **duplicate id throws** `InvalidArgumentException` (`Cannot register entry "%s": already registered.`) — no silent no-op; use `extend()`. The built-in `auto` defaults are the only replaceable entries (that's how a user entry overrides the dispatcher).
- **Factory shapes**: closures, function strings, `Class::method` / `[class, method]` pairs, instantiable class-strings, `EntryFactory` instances, and **objects with `__invoke()` (stored and invoked as-is, never re-instantiated)** are accepted; **plain objects are rejected** — register instances as `fn () => $instance` or `new EntryFactory(...)`; scalars/`null`/malformed pairs are rejected too. Every failure is `InvalidArgumentException` (`Cannot register entry "%s": …`).
- **An alias factory must name a pre-registered id**: any other string (incl. interface, trait, abstract-class, and enum names) becomes an `AliasEntry` only when `has($target)` — otherwise the typo-catcher `InvalidArgumentException`. Class-strings never sniff `__invoke()` — a class entry always builds an instance.
- **`get()` builds per-entry singletons through one error boundary**: `NotFoundException` is only ever constructed at the collector lookup (it always names an absent id — build failures never wear that label); build failures surface as `ResolutionException` (`Failed to resolve "%s": %s`) or propagate as untouched user `Throwable`s; no `Projek\Container\InvalidArgumentException` is constructed inside `get()`. Cache hits dispatch nothing.
- **`EntryResolved` fires only on fresh, non-`auto`, non-alias, object-valued builds — strictly after the cache write**: an alias fires exactly one event carrying the **target's** id; a listener re-entering `get()` of the same id hits the cache; a throwing listener does not un-cache the entry, so the event is not replayed on the next `get()`.
- **`make()` accepts exactly four families** — registered id | instantiable class-string | callable shape | otherwise `InvalidArgumentException` (`Cannot make from "%s": %s`; plain objects included). Always fresh: **never cached, zero events**; `ContainerAware` injection is direct (dispatcher-independent). `$args` is `array`-typed (non-array → native `TypeError`), there is **no `$condition` parameter**, and for class entries `$args` feeds the **constructor** (v1 invoked `__invoke($args)` — use `make([Class::class, '__invoke'], $args)` for that).
- **`extend()` never forces a build** — returns `static`; absent id → `NotFoundException`; non-derivable extension target or a callback without an explicit, non-union, named class return type → `InvalidArgumentException`. Pending decorators apply inside the next build (`get()` **or** `make()`); an already-built entry is decorated immediately (apply-then-append + re-cache), so decorator list and cached value cannot drift.
- **`EntryCollector::offsetUnset()` always throws** `InvalidArgumentException` — entries cannot be removed.
- **ContainerAware injection has two paths**: `get()` goes through the built-in `Events\ListenerProvider` (`EntryResolved` listener) — a custom dispatcher without that provider wired in silently stops injection; `make()` injects directly and never dispatches events.
- **Only two events exist** — `EntryRegistered` (end of `set()`, incl. auto-entry replacement, and `setEventDispatcher()`) and `EntryResolved`; both payloads are readonly, so listeners can no longer redirect ids or replace entries (the four old events are deleted). Neither fires for infrastructure default insertion, `extend()`, cache hits, `make()`, or non-object results. Events raised while the `EventDispatcherInterface` entry is mid-build are queued and flushed FIFO after it is cached.
- **`clone()` copies registrations, it does not share them**: every entry is cloned with its singleton cache reset, the three self-referential auto defaults (`self::class`, `ContainerInterface::class`, `EventDispatcherInterface::class`) are re-pointed to the clone, and the shared handler is rebuilt lazily — later `set()` calls don't propagate between original and clone. Objects captured *inside* user factory closures are shared by reference (a `use`d container keeps capturing the original).
- **Resolver & Handler are container internals** — neither is registered as an entry (`has(ResolverInterface::class)` is `false`; registering that id yourself is inert dead weight), `getResolver()` does not exist, and there is no bootstrap window or invalidation bookkeeping: the shared `Handler` is built lazily as `new Handler(new Resolver($this))` and nulled on `clone()` (its `Resolver` captures the original container). `ClassNameEntry::produce()` reaches the resolver through `$handler->resolver` — callable ≥ 0.4.1's public readonly property — never through container entries.
- **Exception taxonomy**: `NotFoundException extends \RuntimeException implements NotFoundExceptionInterface` (`Container entry "%s" not found.`, `getName()` kept), `ResolutionException extends \RuntimeException implements ContainerExceptionInterface`, `InvalidArgumentException extends \InvalidArgumentException` (no PSR marker). `Container\Exception`, `Container\Resolver`, `UnresolvableArgumentException`, and the four old event classes are **deleted** — specs assert on `Projek\Container\ResolutionException` / `Projek\Container\NotFoundException`.
- **`InvalidArgumentException` construction goes through named static factories**: every IAE in `src/` is thrown as `throw InvalidArgumentException::alreadyRegistered($id)` etc. — one factory per distinct message (18 total); message text and slot normalization (`get_debug_type`, casts) live only in `InvalidArgumentException.php`, never at call sites. A bare `new InvalidArgumentException(sprintf(...))` anywhere in `src/` violates the convention — add a factory instead.
